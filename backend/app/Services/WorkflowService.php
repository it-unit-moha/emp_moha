<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function deductionsEnabled(): bool
    {
        $value = DB::table('system_settings')->where('key', 'automatic_deductions')->value('value');

        return $value === null ? (bool) config('galms.leave.automatic_deductions_enabled') : $value === '1';
    }

    public function audit(string $action, string $entity, ?int $id, array $details = []): void
    {
        DB::table('audit_logs')->insert(['actor_id' => auth()->id(), 'action' => $action, 'entity' => $entity, 'entity_id' => $id, 'details' => json_encode($details), 'created_at' => now()]);
    }

    public function initialize(int $employeeId, int $year): void
    {
        $profile = EmployeeProfile::firstOrCreate(['employee_id' => $employeeId]);
        foreach (['casual' => 21, 'vacation' => $profile->vacation_days ?? 0, 'maternity' => 0, 'paternity' => 0, 'lapsed' => 0] as $type => $opening) {
            DB::table('leave_accounts')->insertOrIgnore(['employee_id' => $employeeId, 'year' => $year, 'type' => $type, 'opening' => $opening]);
        }
    }

    public function balance(object $account): float
    {
        return round((float) $account->opening + (float) DB::table('leave_ledger')->where('account_id', $account->id)->sum('amount'), 2);
    }

    public function accounts(int $year)
    {
        return DB::table('leave_accounts')->join('employee_data', 'employee_data.id', '=', 'leave_accounts.employee_id')
            ->select('leave_accounts.*', 'employee_data.name')->where('year', $year)->orderBy('employee_data.name')->get()
            ->map(function ($account) {
                $account->balance = $this->balance($account);

                return $account;
            });
    }

    public function import(string $path, string $filename): array
    {
        $hash = hash_file('sha256', $path);
        if ($previous = DB::table('attendance_imports')->where('sha256', $hash)->first()) {
            return ['id' => $previous->id, 'inserted' => 0, 'duplicates' => $previous->inserted + $previous->duplicates, 'repeated_file' => true];
        }
        $handle = fopen($path, 'r');
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            }
            if ($header !== ['enroll_id', 'timestamp', 'state']) {
                throw ValidationException::withMessages(['file' => ['Expected CSV header: enroll_id,timestamp,state. Convert device exports to this explicit format; punch codes are not guessed.']]);
            }
            $rows = [];
            $errors = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== 3 || count($rows) >= 50000) {
                    $errors[] = "Row $line: invalid column count or more than 50,000 punches.";
                    break;
                }
                [$enroll, $timestamp, $state] = array_map('trim', $row);
                $profile = EmployeeProfile::where('enroll_id', $enroll)->first();
                $employee = $profile ? Employee::find($profile->employee_id) : null;
                try {
                    $time = Date::createFromFormat('!Y-m-d H:i:s', $timestamp, 'Asia/Colombo');
                } catch (\Throwable) {
                    $time = null;
                }
                if (! $employee || $employee->is_active !== 1 || ! $profile->staff_category || ! $time || $time->format('Y-m-d H:i:s') !== $timestamp || ! in_array($state, ['IN', 'OUT'], true)) {
                    $errors[] = "Row $line: use a mapped active employee with a category, timestamp YYYY-MM-DD HH:MM:SS and state IN or OUT.";
                    if (count($errors) >= 20) {
                        break;
                    }

                    continue;
                }
                $rows[] = ['employee_id' => $employee->id, 'punched_at' => $timestamp, 'state' => $state];
            }
        } finally {
            fclose($handle);
        }
        if ($errors || ! $rows) {
            throw ValidationException::withMessages(['file' => $errors ?: ['The file contains no punches.']]);
        }

        return DB::transaction(function () use ($rows, $filename, $hash) {
            // Unique constraints make identical files and punches safe against concurrent imports.
            $now = now();
            $created = DB::table('attendance_imports')->insertOrIgnore(['filename' => $filename, 'sha256' => $hash, 'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
            $batch = DB::table('attendance_imports')->where('sha256', $hash)->lockForUpdate()->first();
            if (! $created) {
                return ['id' => $batch->id, 'inserted' => 0, 'duplicates' => count($rows), 'repeated_file' => true];
            }
            $inserted = 0;
            $days = [];
            $ids = array_values(array_unique(array_column($rows, 'employee_id')));
            sort($ids);
            EmployeeProfile::whereIn('employee_id', $ids)->orderBy('employee_id')->lockForUpdate()->get();
            foreach ($rows as $row) {
                $inserted += DB::table('attendance_logs')->insertOrIgnore([...$row, 'import_id' => $batch->id]);
                $days[$row['employee_id'].'|'.substr($row['punched_at'], 0, 10)] = [$row['employee_id'], substr($row['punched_at'], 0, 10)];
            }
            foreach ($days as [$employee, $date]) {
                $this->calculate($employee, $date);
            }
            foreach ($days as [$employee, $date]) {
                $this->reconcilePenalty($employee, substr($date, 0, 7));
            }
            $result = ['id' => $batch->id, 'inserted' => $inserted, 'duplicates' => count($rows) - $inserted];
            DB::table('attendance_imports')->where('id', $batch->id)->update([...$result, 'updated_at' => $now]);
            $this->audit('import', 'attendance_imports', $batch->id, $result);

            return $result;
        });
    }

    public function calculate(int $employee, string $date): void
    {
        $profile = EmployeeProfile::where('employee_id', $employee)->first();
        $schedule = config('galms.schedules.'.$profile?->staff_category);
        $logs = DB::table('attendance_logs')->where('employee_id', $employee)->whereDate('punched_at', $date)->orderBy('punched_at')->get();
        $in = $logs->firstWhere('state', 'IN')?->punched_at;
        $out = $logs->where('state', 'OUT')->last()?->punched_at;
        $holiday = Date::parse($date)->isWeekend() || DB::table('holidays')->where('date', $date)->exists();
        $late = 0;
        $required = null;
        $status = 'review';
        if ($schedule && $in && $out && $out > $in) {
            $start = Date::parse($date.' '.$schedule['start'], 'Asia/Colombo');
            $late = max(0, (int) ceil($start->diffInSeconds(Date::parse($in, 'Asia/Colombo'), false) / 60));
            $required = Date::parse($date.' '.$schedule['end'], 'Asia/Colombo')->addMinutes($late)->format('Y-m-d H:i:s');
            $status = $holiday ? 'non_working_day' : ($late ? ($out >= $required ? 'late_covered' : 'late_uncovered') : ($out < $required ? 'early_departure' : 'on_time'));
        }
        // Approved leave covering the arrival window needs human review, never an automatic penalty.
        if ($late && DB::table('leave_applications')->where('employee_id', $employee)->where('status', 'approved')->where('start_date', '<=', $date)->where('end_date', '>=', $date)->exists()) {
            $status = 'leave_review';
        }
        DB::table('attendance_days')->updateOrInsert(['employee_id' => $employee, 'date' => $date], ['actual_in' => $in, 'actual_out' => $out, 'late_minutes' => $late, 'required_out' => $required, 'status' => $status, 'updated_at' => now(), 'created_at' => now()]);
    }

    public function reconcilePenalty(int $employee, string $month): void
    {
        if (! $this->deductionsEnabled() && ! DB::table('leave_ledger')->where('reference', 'like', 'penalty:'.$employee.':'.$month.':%')->exists()) {
            return;
        }
        $this->initialize($employee, (int) substr($month, 0, 4));
        $account = DB::table('leave_accounts')->where(['employee_id' => $employee, 'year' => (int) substr($month, 0, 4), 'type' => 'casual'])->lockForUpdate()->first();
        $count = DB::table('attendance_days')->where('employee_id', $employee)->where('date', 'like', $month.'%')->where('status', 'late_uncovered')->count();
        $target = $this->deductionsEnabled() ? -0.5 * intdiv($count, 3) : 0;
        $ref = 'penalty:'.$employee.':'.$month;
        $posted = (float) DB::table('leave_ledger')->where('reference', 'like', $ref.':%')->sum('amount');
        $delta = round($target - $posted, 2);
        if (! $delta) {
            return;
        }
        DB::table('leave_ledger')->insert(['reference' => $ref.':'.Str::uuid(), 'account_id' => $account->id, 'amount' => $delta, 'description' => 'Lateness reconciliation '.$month.': '.$count.' uncovered days; net penalty '.abs($target), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('reconcile_penalty', 'leave_accounts', $account->id, ['month' => $month, 'adjustment' => $delta, 'net_amount' => $target, 'uncovered_days' => $count]);
    }

    public function refreshDates(string $start, string $end, ?int $employeeId = null): void
    {
        DB::transaction(function () use ($start, $end, $employeeId) {
            $query = DB::table('attendance_days')->whereBetween('date', [$start, $end]);
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }
            $days = $query->orderBy('employee_id')->get();
            foreach ($days as $day) {
                EmployeeProfile::where('employee_id', $day->employee_id)->lockForUpdate()->first();
                $this->calculate($day->employee_id, $day->date);
                $this->reconcilePenalty($day->employee_id, substr($day->date, 0, 7));
            }
        });
    }

    public function requestLeave(array $data): object
    {
        return DB::transaction(function () use ($data) {
            $profile = EmployeeProfile::where('employee_id', $data['employee_id'])->lockForUpdate()->first();
            if (! $profile?->staff_category) {
                throw ValidationException::withMessages(['employee_id' => ['Assign the employee category first.']]);
            }
            $start = Date::parse($data['start_date']);
            $end = Date::parse($data['end_date']);
            if ($start->year !== $end->year) {
                throw ValidationException::withMessages(['end_date' => ['Submit separate applications for each leave year.']]);
            }
            $short = str_starts_with($data['type'], 'short_');
            $overlap = DB::table('leave_applications')->where('employee_id', $data['employee_id'])->whereIn('status', ['pending', 'approved'])->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date']);
            if ($short) {
                $overlap->where(fn ($q) => $q->where('type', 'not like', 'short_%')->orWhere('type', $data['type']));
            }
            if ($overlap->exists()) {
                throw ValidationException::withMessages(['start_date' => ['An active leave request already overlaps these dates.']]);
            }
            $short = str_starts_with($data['type'], 'short_');
            $days = 0;
            if ($short) {
                if ($profile->staff_category !== 'other' || ! $start->isSameDay($end) || $start->isWeekend() || DB::table('holidays')->where('date', $data['start_date'])->exists()) {
                    throw ValidationException::withMessages(['type' => ['Short leave is for Other Staff on one working day.']]);
                }
                if (empty($data['minutes']) || ! app(LeaveRulesService::class)->validateShortLeave($data['minutes'], 0)) {
                    throw ValidationException::withMessages(['minutes' => ['Short leave must be between 1 and 90 minutes.']]);
                }
                $used = DB::table('leave_applications')->where('employee_id', $data['employee_id'])->where('type', 'like', 'short_%')->whereIn('status', ['pending', 'approved'])->whereBetween('start_date', [$start->startOfMonth()->toDateString(), $start->endOfMonth()->toDateString()])->count();
                if ($used >= 2) {
                    throw ValidationException::withMessages(['short_leave_limit' => ['Two short leaves are already reserved this month. Select half-day casual leave instead.']]);
                }
            } else {
                for ($day = $start; $day <= $end; $day = $day->addDay()) {
                    $foreign = $data['scope'] === 'foreign' && $data['type'] === 'vacation';
                    if ($foreign || ! $day->isWeekend() && ! DB::table('holidays')->where('date', $day->toDateString())->exists()) {
                        $days++;
                    }
                }
                if (! empty($data['half_day'])) {
                    if ($data['type'] !== 'casual' || ! $start->isSameDay($end) || $days !== 1) {
                        throw ValidationException::withMessages(['half_day' => ['Half day is valid for casual leave on one working day.']]);
                    }
                    $days = 0.5;
                }
                if (! $days) {
                    throw ValidationException::withMessages(['start_date' => ['The range contains no chargeable working days.']]);
                }
                if ($data['type'] === 'maternity' && $days > 84 || $data['type'] === 'paternity' && $days > 3) {
                    throw ValidationException::withMessages(['end_date' => ['The requested duration exceeds the configured entitlement.']]);
                }
            }
            if ($data['scope'] === 'foreign' && $data['type'] !== 'vacation') {
                throw ValidationException::withMessages(['scope' => ['Foreign leave counting is available only for vacation leave.']]);
            }
            $id = DB::table('leave_applications')->insertGetId(['employee_id' => $data['employee_id'], 'type' => $data['type'], 'scope' => $data['scope'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'minutes' => $short ? $data['minutes'] : null, 'days' => $days, 'reason' => $data['reason'], 'requested_by' => auth()->id(), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->audit('request', 'leave_applications', $id);

            return DB::table('leave_applications')->find($id);
        });
    }

    public function reviewLeave(int $id, string $decision): void
    {
        DB::transaction(function () use ($id, $decision) {
            $initial = DB::table('leave_applications')->find($id);
            abort_unless($initial, 404);
            EmployeeProfile::where('employee_id', $initial->employee_id)->lockForUpdate()->first();
            $request = DB::table('leave_applications')->where('id', $id)->lockForUpdate()->first();
            if ($decision === 'cancelled' && in_array($request->status, ['pending', 'approved'])) {
                $ledger = DB::table('leave_ledger')->where('reference', 'application:'.$id)->first();
                if ($ledger) {
                    DB::table('leave_ledger')->insert(['account_id' => $ledger->account_id, 'amount' => -(float) $ledger->amount, 'reference' => 'cancellation:'.$id, 'description' => 'Cancelled leave approval #'.$id, 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('leave_applications')->where('id', $id)->update(['status' => 'cancelled', 'reviewed_by' => auth()->id(), 'updated_at' => now()]);
                $this->audit('cancelled', 'leave_applications', $id);
                $this->refreshDates($request->start_date, $request->end_date);

                return;
            }
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['status' => ['This request has already been reviewed.']]);
            }
            if ($decision === 'approved' && str_starts_with($request->type, 'short_') && (Date::parse($request->start_date)->isWeekend() || DB::table('holidays')->where('date', $request->start_date)->exists())) {
                throw ValidationException::withMessages(['status' => ['Short leave must be on a working day under the current calendar.']]);
            }
            if ($decision === 'approved' && ! str_starts_with($request->type, 'short_')) {
                $days = 0;
                for ($day = Date::parse($request->start_date); $day <= Date::parse($request->end_date); $day = $day->addDay()) {
                    if ($request->scope === 'foreign' || ! $day->isWeekend() && ! DB::table('holidays')->where('date', $day->toDateString())->exists()) {
                        $days++;
                    }
                }
                if ((float) $request->days === 0.5 && $days === 1) {
                    $days = 0.5;
                }
                if (! $days || $request->type === 'maternity' && $days > 84 || $request->type === 'paternity' && $days > 3) {
                    throw ValidationException::withMessages(['status' => ['The current calendar produces an invalid eligible leave duration.']]);
                }
                $request->days = $days;
                DB::table('leave_applications')->where('id', $id)->update(['days' => $days]);
                $this->initialize($request->employee_id, (int) substr($request->start_date, 0, 4));
                $account = DB::table('leave_accounts')->where(['employee_id' => $request->employee_id, 'year' => (int) substr($request->start_date, 0, 4), 'type' => $request->type])->lockForUpdate()->first();
                if ($this->balance($account) < (float) $request->days) {
                    throw ValidationException::withMessages(['status' => ['Insufficient eligible leave balance. Verify and adjust opening credits before approval.']]);
                }
                DB::table('leave_ledger')->insert(['account_id' => $account->id, 'amount' => -(float) $request->days, 'reference' => 'application:'.$id, 'description' => $request->type.' leave approved', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('leave_applications')->where('id', $id)->update(['status' => $decision, 'reviewed_by' => auth()->id(), 'updated_at' => now()]);
            $this->audit($decision, 'leave_applications', $id);
            $this->refreshDates($request->start_date, $request->end_date);
        });
    }
}
