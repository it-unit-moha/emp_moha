<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function pdf(string $type, string $month, ?int $employee = null)
    {
        $from = Date::createFromFormat('!Y-m', $month, 'Asia/Colombo')->startOfMonth();
        $to = $from->endOfMonth();
        if ($type === 'attendance') {
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->select('employee_data.name', 'employee_data.emp_no', 'attendance_days.*')->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            $headers = ['Employee / service no.', 'Date', 'IN', 'OUT', 'Late min.', 'Required OUT', 'Status', 'Short leave', 'Leave debit'];
            $recorded = $query->get()->keyBy(fn ($r) => $r->employee_id.'|'.$r->date);
            $staff = DB::table('employee_data')->orderBy('name');
            if ($employee) {
                $staff->where('id', $employee);
            }
            $holidays = DB::table('holidays')->whereBetween('date', [$from->toDateString(), $to->toDateString()])->pluck('name', 'date');
            $applications = DB::table('leave_applications')->where('status', 'approved')->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())->get()->groupBy('employee_id');
            $rows = [];
            foreach ($staff->get() as $person) {
                for ($day = $from; $day <= $to; $day = $day->addDay()) {
                    $date = $day->toDateString();
                    $record = $recorded->get($person->id.'|'.$date);
                    $working = ! $day->isWeekend() && ! $holidays->has($date);
                    $short = 0;
                    $debit = 0;
                    foreach ($applications->get($person->id, collect()) as $leave) {
                        if ($leave->start_date > $date || $leave->end_date < $date) {
                            continue;
                        }
                        if (str_starts_with($leave->type, 'short_')) {
                            $short += $leave->minutes;
                        } elseif ($working || $leave->scope === 'foreign') {
                            $debit += min(1, (float) $leave->days);
                        }
                    }
                    $status = $record?->status ?? ($debit ? 'approved_leave' : (! $working ? ($holidays->get($date) ?? 'weekend') : ($date > now('Asia/Colombo')->toDateString() ? 'not_due' : 'no_record')));
                    $rows[] = [$person->name.' / '.$person->emp_no, $date, $record?->actual_in, $record?->actual_out, $record?->late_minutes, $record?->required_out, $status, $short ? $short.' min' : '-', $debit ?: '-'];
                }
            }
            $notes = 'Every calendar day is shown. No-record days are not automatically classified as absence; roster/effective employment dates and duty exceptions require verification. Daily approved leave allocations are shown separately from monthly late-penalty ledger entries below.';
            $penaltyQuery = DB::table('leave_ledger')->join('leave_accounts', 'leave_accounts.id', '=', 'leave_ledger.account_id')->join('employee_data', 'employee_data.id', '=', 'leave_accounts.employee_id')->where('reference', 'like', 'penalty:%:'.$month.':%')->select('employee_data.name', 'leave_ledger.amount');
            if ($employee) {
                $penaltyQuery->where('leave_accounts.employee_id', $employee);
            }
            $penalties = $penaltyQuery->get();
        } elseif ($type === 'leave-utilization') {
            $headers = ['Division', 'Employee', 'Leave type', 'Opening (month)', 'Credits', 'Debits', 'Remaining (month end)'];
            $query = DB::table('leave_accounts')->join('employee_data', 'employee_data.id', '=', 'leave_accounts.employee_id')->leftJoin('division', 'division.id', '=', 'employee_data.division')->select('leave_accounts.*', 'employee_data.name', 'division.division')->where('year', $from->year);
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            $rows = $query->orderBy('division.division')->orderBy('name')->get()->map(function ($a) use ($from, $to) {
                $ledger = DB::table('leave_ledger')->where('account_id', $a->id);
                $opening = (float) $a->opening + (float) (clone $ledger)->where('created_at', '<', $from->setTimezone('UTC'))->sum('amount');
                $credits = (float) (clone $ledger)->whereBetween('created_at', [$from->setTimezone('UTC'), $to->setTimezone('UTC')])->where('amount', '>', 0)->sum('amount');
                $debits = -(float) (clone $ledger)->whereBetween('created_at', [$from->setTimezone('UTC'), $to->setTimezone('UTC')])->where('amount', '<', 0)->sum('amount');

                return [$a->division ?? 'Unassigned', $a->name, $a->type, $opening, $credits, $debits, $opening + $credits - $debits];
            })->all();
            $notes = 'Ledger posted-date basis: opening includes transactions posted before the month. Debits include approved leave and reconciled penalties posted in the month. Eligibility and opening-credit approvals remain the administrator’s responsibility.';
        } else {
            $headers = ['Division', 'Employee', 'Uncovered late days', 'Calculated casual penalty'];
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->leftJoin('division', 'division.id', '=', 'employee_data.division')->where('date', 'like', $month.'%')->where('date', '<=', now('Asia/Colombo')->toDateString())->where('attendance_days.status', 'late_uncovered')->selectRaw('employee_data.id, employee_data.name, division.division, COUNT(*) as days')->groupBy('employee_data.id', 'employee_data.name', 'division.division')->havingRaw('COUNT(*) >= 3');
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            $rows = $query->orderBy('division.division')->orderBy('employee_data.name')->get()->map(fn ($r) => [$r->division ?? 'Unassigned', $r->name, $r->days, intdiv($r->days, 3) * 0.5])->all();
            $notes = 'As of '.now('Asia/Colombo')->format('Y-m-d H:i').' Asia/Colombo. Review duty and leave exceptions before relying on this warning. Scheduled snapshots are generated on the 25th.';
        }

        return Pdf::loadView('reports.monthly', ['type' => $type, 'month' => $month, 'headers' => $headers, 'rows' => $rows, 'notes' => $notes, 'penalties' => $penalties ?? collect()])->setPaper('a4', 'landscape');
    }
}
