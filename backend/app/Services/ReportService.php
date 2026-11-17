<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;

class ReportService
{
<<<<<<< Updated upstream
    public function pdf(string $type, string $month, ?int $employee = null)
=======
    public function pdf(string $type, string $month, ?int $employee = null, ?int $division = null, ?string $date = null)
>>>>>>> Stashed changes
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $from = Date::createFromFormat('!Y-m', $month, 'Asia/Colombo')->startOfMonth();
        $to = $from->endOfMonth();
<<<<<<< Updated upstream
        if ($type === 'attendance') {
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->select('employee_data.name', 'employee_data.emp_no', 'attendance_days.*')->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
=======
        $scope = $division ? DB::table('division')->where('id', $division)->value('division') : 'All divisions';
        if ($employee) {
            $scope .= ' / '.DB::table('employee_data')->where('id', $employee)->value('name');
        }

        // If attendance was requested without a single employee:
        // - If a specific calendar date is provided, generate the Daily Attendance Report.
        // - Otherwise, generate the Monthly Attendance & Divisional Summary (to prevent unpaginated 20,000-row crashes).
        if ($type === 'attendance' && ! $employee && ! $date) {
            $type = 'divisional';
        }

        if ($type === 'attendance' && $date) {
            $reportTitle = 'Daily Attendance Report';
            $headers = ['Division', 'Employee / service no.', 'IN', 'OUT', 'Late min.', 'Required OUT', 'Evaluation', 'Remarks'];

            $staff = DB::table('employee_data')
                ->where('employee_data.is_active', 1)
                ->leftJoin('division', 'division.id', '=', 'employee_data.division')
                ->select('employee_data.*', 'division.division as division_name')
                ->orderByRaw(DivisionOrder::sql())
                ->orderBy('division.division')
                ->orderBy('employee_data.name');

            if ($division) {
                $staff->where('employee_data.division', $division);
            }
            if ($employee) {
                $staff->where('employee_data.id', $employee);
            }

            $attendanceDays = DB::table('attendance_days')
                ->where('date', $date)
                ->get()
                ->keyBy('employee_id');

            $holiday = DB::table('holidays')->where('date', $date)->value('name');
            $targetDate = Date::parse($date, 'Asia/Colombo');
            $isWeekend = $targetDate->isWeekend();

            $applications = DB::table('leave_applications')
                ->where('status', 'approved')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->get()
                ->groupBy('employee_id');

            $rows = [];
            foreach ($staff->get() as $person) {
                $rec = $attendanceDays->get($person->id);
                $hasLeave = $applications->has($person->id);

                $short = 0;
                $leaveText = null;
                if ($hasLeave) {
                    foreach ($applications->get($person->id) as $leave) {
                        if (str_starts_with($leave->type, 'short_')) {
                            $short += $leave->minutes;
                        } else {
                            $leaveText = 'Approved '.ucwords(str_replace('_', ' ', $leave->type));
                        }
                    }
                }

                $status = $rec?->evaluation ?? ($leaveText ?: ($holiday ?: ($isWeekend ? 'weekend' : ($date > now('Asia/Colombo')->toDateString() ? 'not_due' : 'no_record'))));
                $remarks = $rec?->remarks ?? ($short ? "Approved Short Leave: {$short} mins." : ($leaveText ?: str_replace('_', ' ', $status)));

                $rows[] = [
                    $person->division_name ?? 'Unassigned',
                    $person->name.' / '.($person->emp_no ?? '—'),
                    $rec?->actual_in ? substr($rec->actual_in, 11, 5) : '—',
                    $rec?->actual_out ? substr($rec->actual_out, 11, 5) : '—',
                    $rec?->late_minutes ?? '0',
                    $rec?->required_out ? substr($rec->required_out, 11, 5) : '—',
                    ucwords(str_replace('_', ' ', $status)),
                    $remarks,
                ];
            }
            $notes = "Daily attendance records for {$date} in Asia/Colombo time. Generated for active employees only.";
        } elseif ($type === 'divisional') {
            $reportTitle = 'Monthly Attendance & Divisional Summary';
            $headers = ['Division', 'Employee / service no.', 'Recorded days', 'Late arrivals', 'Late covered', 'Short leaves', 'Half-day conversions', 'Half-day total', 'Needs review'];
            $staff = DB::table('employee_data')->where('is_active', 1)->leftJoin('division', 'division.id', '=', 'employee_data.division')->select('employee_data.*', 'division.division as division_name')->orderByRaw(DivisionOrder::sql())->orderBy('division.division')->orderBy('employee_data.name');
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
            $notes = 'Employees are grouped in official division order, then by name, with division totals. Counts reflect uploaded attendance and approved short leaves; missing records are not inferred as absence. LC = Late Come; SL = Short Leave; HD = Half Day. Half-day conversions are evaluated outcomes; balance postings follow the automatic-deduction setting.';
        } elseif ($type === 'attendance') {
            $reportTitle = 'Monthly Employee Attendance Record';
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->where('employee_data.is_active', 1)->select('employee_data.name', 'employee_data.emp_no', 'attendance_days.*')->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
>>>>>>> Stashed changes
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            $headers = ['Employee / service no.', 'Date', 'IN', 'OUT', 'Late min.', 'Required OUT', 'Status', 'Short leave', 'Leave debit'];
            $recorded = $query->get()->keyBy(fn ($r) => $r->employee_id.'|'.$r->date);
<<<<<<< Updated upstream
            $staff = DB::table('employee_data')->orderBy('name');
=======
            $staff = DB::table('employee_data')->where('is_active', 1)->leftJoin('division', 'division.id', '=', 'employee_data.division')->select('employee_data.*', 'division.division as division_name')->orderByRaw(DivisionOrder::sql())->orderBy('division.division')->orderBy('employee_data.name');
            if ($division) {
                $staff->where('employee_data.division', $division);
            }
>>>>>>> Stashed changes
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
            $penaltyQuery = DB::table('leave_ledger')->join('leave_accounts', 'leave_accounts.id', '=', 'leave_ledger.account_id')->join('employee_data', 'employee_data.id', '=', 'leave_accounts.employee_id')->where('employee_data.is_active', 1)->where('reference', 'like', 'penalty:%:'.$month.':%')->select('employee_data.name', 'leave_ledger.amount');
            if ($employee) {
                $penaltyQuery->where('leave_accounts.employee_id', $employee);
            }
            $penalties = $penaltyQuery->get();
        } elseif ($type === 'leave-utilization') {
            $reportTitle = 'Leave Utilization Report';
            $headers = ['Division', 'Employee', 'Leave type', 'Opening (month)', 'Credits', 'Debits', 'Remaining (month end)'];
            $query = DB::table('leave_accounts')->join('employee_data', 'employee_data.id', '=', 'leave_accounts.employee_id')->where('employee_data.is_active', 1)->leftJoin('division', 'division.id', '=', 'employee_data.division')->select('leave_accounts.*', 'employee_data.name', 'division.division')->where('year', $from->year);
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
<<<<<<< Updated upstream
            $headers = ['Division', 'Employee', 'Uncovered late days', 'Calculated casual penalty'];
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->leftJoin('division', 'division.id', '=', 'employee_data.division')->where('date', 'like', $month.'%')->where('date', '<=', now('Asia/Colombo')->toDateString())->where('attendance_days.status', 'late_uncovered')->selectRaw('employee_data.id, employee_data.name, division.division, COUNT(*) as days')->groupBy('employee_data.id', 'employee_data.name', 'division.division')->havingRaw('COUNT(*) >= 3');
=======
            $reportTitle = 'Late Warning Report';
            $headers = ['Division', 'Employee', 'Half-day conversions', 'Calculated casual penalty'];
            $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->where('employee_data.is_active', 1)->leftJoin('division', 'division.id', '=', 'employee_data.division')->where('date', 'like', $month.'%')->where('date', '<=', now('Asia/Colombo')->toDateString())->where('attendance_days.evaluation', 'half_day')->selectRaw('employee_data.id, employee_data.name, division.division, COUNT(*) as days')->groupBy('employee_data.id', 'employee_data.name', 'division.division');
>>>>>>> Stashed changes
            if ($employee) {
                $query->where('employee_id', $employee);
            }
            $rows = $query->orderBy('division.division')->orderBy('employee_data.name')->get()->map(fn ($r) => [$r->division ?? 'Unassigned', $r->name, $r->days, intdiv($r->days, 3) * 0.5])->all();
            $notes = 'As of '.now('Asia/Colombo')->format('Y-m-d H:i').' Asia/Colombo. Review duty and leave exceptions before relying on this warning. Scheduled snapshots are generated on the 25th.';
        }

<<<<<<< Updated upstream
        return Pdf::loadView('reports.monthly', ['type' => $type, 'month' => $month, 'headers' => $headers, 'rows' => $rows, 'notes' => $notes, 'penalties' => $penalties ?? collect()])->setPaper('a4', 'landscape');
=======
        return Pdf::loadView('reports.monthly', [
            'type' => $type,
            'reportTitle' => $reportTitle,
            'month' => $month,
            'date' => $date,
            'scope' => $scope,
            'headers' => $headers,
            'rows' => $rows,
            'notes' => $notes,
            'penalties' => $penalties ?? collect(),
        ])->setPaper('a4', 'landscape');
>>>>>>> Stashed changes
    }
}
