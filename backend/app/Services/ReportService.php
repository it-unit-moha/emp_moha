<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function pdf(string $type, string $month, ?int $employee = null, ?int $division = null)
    {
        $from = Date::createFromFormat('!Y-m', $month, 'Asia/Colombo')->startOfMonth();
        $to = $from->endOfMonth();
        $scope = $division ? DB::table('division')->where('id', $division)->value('division') : 'All divisions';
        if ($employee) {
            $scope .= ' / '.DB::table('employee_data')->where('id', $employee)->value('name');
        }
        if ($type === 'divisional') {
            $headers = ['Division', 'Employee / service no.', 'Recorded days', 'Late arrivals', 'Late covered', 'Short leaves', 'Half-day conversions', 'Half-day total', 'Needs review'];
            $staff = DB::table('employee_data')->leftJoin('division', 'division.id', '=', 'employee_data.division')->select('employee_data.*', 'division.division as division_name')->orderByRaw(DivisionOrder::sql())->orderBy('division.division')->orderBy('employee_data.name');
            if ($division) {
                $staff->where('employee_data.division', $division);
            }
            if ($employee) {
                $staff->where('employee_data.id', $employee);
            }
            $staff = $staff->get();
            $days = DB::table('attendance_days')->where('date', 'like', $month.'%')->whereIn('employee_id', $staff->pluck('id'))->get()->groupBy('employee_id');
            $shortLeaves = DB::table('leave_applications')->where('status', 'approved')->where('type', 'like', 'short_%')->where('start_date', 'like', $month.'%')->whereIn('employee_id', $staff->pluck('id'))->get()->groupBy('employee_id');
            $rows = $staff->map(function ($p) use ($days, $shortLeaves) {
                $d = $days->get($p->id, collect());
                $half = $d->where('evaluation', 'half_day')->count();

                return [$p->division_name ?? 'Unassigned', $p->name.' / '.$p->emp_no, $d->count(), $d->where('late_minutes', '>', 0)->count(), $d->where('evaluation', 'late_covered')->count(), $d->where('evaluation', 'short_leave')->count() + $shortLeaves->get($p->id, collect())->count(), $half, $half * 0.5, $d->whereIn('evaluation', ['review', 'policy_review', 'leave_review'])->count()];
            })->all();
            $rows = collect($rows)->groupBy(fn ($row) => $row[0])->flatMap(function ($group) {
                $total = [$group->first()[0], 'Division total'];
                for ($column = 2; $column <= 8; $column++) {
                    $total[] = $group->sum(fn ($row) => $row[$column]);
                }

                return [...$group->all(), $total];
            })->all();
            $notes = 'Employees are grouped in the supplied official division order, then by name, with division totals. Counts reflect uploaded attendance and approved short leaves; missing records are not inferred as absence. LC = Late Come; SL = Short Leave; HD = Half Day. Half-day conversions are evaluated outcomes; balance postings follow the automatic-deduction setting.';
        } elseif ($type === 'attendance') {
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->select('employee_data.name', 'employee_data.emp_no', 'attendance_days.*')->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            if ($division) {
                $query->where('employee_data.division', $division);
            }
            $headers = ['Division / employee / service no.', 'Date', 'IN', 'OUT', 'Late min.', 'Required OUT', 'Evaluation', 'Remarks', 'Leave debit'];
            $recorded = $query->get()->keyBy(fn ($r) => $r->employee_id.'|'.$r->date);
            $staff = DB::table('employee_data')->leftJoin('division', 'division.id', '=', 'employee_data.division')->select('employee_data.*', 'division.division as division_name')->orderByRaw(DivisionOrder::sql())->orderBy('division.division')->orderBy('employee_data.name');
            if ($division) {
                $staff->where('employee_data.division', $division);
            }
            if ($employee) {
                $staff->where('employee_data.id', $employee);
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
                    $status = $record?->evaluation ?? ($debit ? 'approved_leave' : (! $working ? ($holidays->get($date) ?? 'weekend') : ($date > now('Asia/Colombo')->toDateString() ? 'not_due' : 'no_record')));
                    $remarks = $record?->remarks ?? ($short ? 'SL - Approved Short Leave: '.$short.' minutes.' : str_replace('_', ' ', $status));
                    $rows[] = [($person->division_name ?? 'Unassigned').' / '.$person->name.' / '.$person->emp_no, $date, $record?->actual_in ? substr($record->actual_in, 11, 5) : null, $record?->actual_out ? substr($record->actual_out, 11, 5) : null, $record?->late_minutes, $record?->required_out ? substr($record->required_out, 11, 5) : null, str_replace('_', ' ', $status), $remarks, $debit ?: '-'];
                }
            }
            $notes = 'Every calendar day is shown. No-record days are not automatically classified as absence; roster/effective employment dates and duty exceptions require verification. Daily approved leave allocations are shown separately from monthly late-penalty ledger entries below.';
            $penaltyQuery = DB::table('leave_ledger')->join('leave_accounts', 'leave_accounts.id', '=', 'leave_ledger.account_id')->join('employee_data', 'employee_data.id', '=', 'leave_accounts.employee_id')->where('reference', 'like', 'penalty:%:'.$month.':%')->select('employee_data.name', 'leave_ledger.amount');
            if ($employee) {
                $penaltyQuery->where('leave_accounts.employee_id', $employee);
            }
            if ($division) {
                $penaltyQuery->where('employee_data.division', $division);
            }
            $penalties = $penaltyQuery->get();
        } elseif ($type === 'leave-utilization') {
            $headers = ['Division', 'Employee', 'Leave type', 'Opening (month)', 'Credits', 'Debits', 'Remaining (month end)'];
            $query = DB::table('leave_accounts')->join('employee_data', 'employee_data.id', '=', 'leave_accounts.employee_id')->leftJoin('division', 'division.id', '=', 'employee_data.division')->select('leave_accounts.*', 'employee_data.name', 'division.division')->where('year', $from->year);
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            if ($division) {
                $query->where('employee_data.division', $division);
            }
            $rows = $query->orderByRaw(DivisionOrder::sql())->orderBy('division.division')->orderBy('name')->get()->map(function ($a) use ($from, $to) {
                $ledger = DB::table('leave_ledger')->where('account_id', $a->id);
                $opening = (float) $a->opening + (float) (clone $ledger)->where('created_at', '<', $from->setTimezone('UTC'))->sum('amount');
                $credits = (float) (clone $ledger)->whereBetween('created_at', [$from->setTimezone('UTC'), $to->setTimezone('UTC')])->where('amount', '>', 0)->sum('amount');
                $debits = -(float) (clone $ledger)->whereBetween('created_at', [$from->setTimezone('UTC'), $to->setTimezone('UTC')])->where('amount', '<', 0)->sum('amount');

                return [$a->division ?? 'Unassigned', $a->name, $a->type, $opening, $credits, $debits, $opening + $credits - $debits];
            })->all();
            $notes = 'Ledger posted-date basis: opening includes transactions posted before the month. Debits include approved leave and reconciled penalties posted in the month. Eligibility and opening-credit approvals remain the administrator’s responsibility.';
        } else {
            $headers = ['Division', 'Employee', 'Half-day conversions', 'Calculated casual penalty'];
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->leftJoin('division', 'division.id', '=', 'employee_data.division')->where('date', 'like', $month.'%')->where('date', '<=', now('Asia/Colombo')->toDateString())->where('attendance_days.evaluation', 'half_day')->selectRaw('employee_data.id, employee_data.name, division.division, COUNT(*) as days')->groupBy('employee_data.id', 'employee_data.name', 'division.division');
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            if ($division) {
                $query->where('employee_data.division', $division);
            }
            $rows = $query->orderByRaw(DivisionOrder::sql())->orderBy('division.division')->orderBy('employee_data.name')->get()->map(fn ($r) => [$r->division ?? 'Unassigned', $r->name, $r->days, $r->days * 0.5])->all();
            $notes = 'As of '.now('Asia/Colombo')->format('Y-m-d H:i').' Asia/Colombo. Review duty and leave exceptions before relying on this warning. Scheduled snapshots are generated on the 25th.';
        }

        return Pdf::loadView('reports.monthly', ['type' => $type, 'month' => $month, 'headers' => $headers, 'rows' => $rows, 'notes' => $scope.'. '.$notes, 'penalties' => $penalties ?? collect()])->setPaper('a4', 'landscape');
    }
}
