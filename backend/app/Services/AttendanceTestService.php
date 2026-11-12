<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceTestService
{
    public function available(): bool
    {
        return (app()->environment('local') && config('database.default') === 'sqlite' && realpath(config('database.connections.sqlite.database')) === realpath(database_path('demo.sqlite')))
            || (app()->environment('testing') && config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === ':memory:');
    }

    public function details(object $run): array
    {
        $manifest = json_decode($run->manifest, true);
        $files = array_fill(0, 8, []);
        $add = function (int $device, int $person, string $timestamp, string $state) use (&$files) {
            $files[$device][] = sprintf('%d%02d', $device + 1, $person + 1)."\t$timestamp\t1\t$state\t0\t0";
        };
        foreach (['02', '03', '04', '05', '06'] as $index => $day) {
            $in = ($index * 2) % 8;
            $out = ($in + 3) % 8;
            $add($in, 0, "2026-11-$day 08:45:00", '0');
            $add($out, 0, "2026-11-$day ".($index < 2 ? '16:30:00' : '16:15:00'), '1');
            $add(($out + 1) % 8, 0, "2026-11-$day ".($index < 2 ? '16:30:00' : '16:15:00'), '1');
        }
        $add(1, 1, '2026-11-02 08:00:00', '0');
        $add(4, 1, '2026-11-02 16:45:00', '1');
        $add(3, 1, '2026-11-03 08:10:00', '0');
        $add(6, 1, '2026-11-03 16:55:00', '1');
        $add(5, 1, '2026-11-04 08:00:00', '0');
        $fixtures = [];
        foreach ($files as $index => $rows) {
            sort($rows);
            $fixtures[] = ['device_id' => $manifest['devices'][$index], 'filename' => 'TEMP-'.$run->token.'-machine-'.($index + 1).'.txt', 'content' => implode("\n", $rows)."\n"];
        }

        return ['id' => $run->id, 'token' => $run->token, 'division_id' => $manifest['division'], 'employee_ids' => $manifest['employees'], 'month' => '2026-11', 'fixtures' => $fixtures];
    }

    public function create(): array
    {
        abort_unless($this->available(), 403, 'Temporary test data is available only in the local demo database.');

        return DB::transaction(function () {
            if (DB::table('attendance_test_runs')->exists()) {
                throw ValidationException::withMessages(['test' => ['Delete the current temporary test session before starting another.']]);
            }
            $token = strtolower(Str::random(8));
            $division = DB::table('division')->insertGetId(['division' => 'Temporary test '.$token, 'status' => 1]);
            $employees = [];
            foreach (['other', 'pl'] as $index => $category) {
                $employee = Employee::create(['name' => 'Temporary test '.$token.' '.($index ? 'PL Staff' : 'Other Staff'), 'emp_no' => 'T'.$token.($index + 1), 'division' => $division, 'is_active' => 1]);
                EmployeeProfile::create(['employee_id' => $employee->id, 'enroll_id' => 'TMP_'.$token.'_'.($index + 1), 'staff_category' => $category, 'service_grade' => 'Temporary test']);
                app(WorkflowService::class)->initialize($employee->id, 2026);
                $employees[] = $employee->id;
            }
            $devices = [];
            for ($index = 0; $index < 8; $index++) {
                $device = DB::table('attendance_devices')->insertGetId(['name' => 'Temporary '.$token.' machine '.($index + 1), 'format' => 'device_text', 'in_code' => '0', 'out_code' => '1', 'shared_enrollment' => false, 'created_at' => now(), 'updated_at' => now()]);
                foreach ($employees as $person => $employee) {
                    DB::table('attendance_device_enrollments')->insert(['device_id' => $device, 'employee_id' => $employee, 'enroll_id' => sprintf('%d%02d', $index + 1, $person + 1)]);
                }
                $devices[] = $device;
            }
            $id = DB::table('attendance_test_runs')->insertGetId(['token' => $token, 'manifest' => json_encode(['division' => $division, 'employees' => $employees, 'devices' => $devices]), 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);

            return $this->details(DB::table('attendance_test_runs')->find($id));
        });
    }

    public function import(int $id): array
    {
        abort_unless($this->available(), 403);
        $run = DB::table('attendance_test_runs')->find($id);
        abort_unless($run, 404);
        $paths = [];
        $files = [];
        try {
            foreach ($this->details($run)['fixtures'] as $fixture) {
                $path = tempnam(sys_get_temp_dir(), 'galms-test-');
                $paths[] = $path;
                file_put_contents($path, $fixture['content']);
                $files[] = ['path' => $path, 'filename' => $fixture['filename'], 'device_id' => $fixture['device_id']];
            }

            return app(AttendanceImportService::class)->importMany($files);
        } finally {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function cleanup(int $id): array
    {
        abort_unless($this->available(), 403);

        return DB::transaction(function () use ($id) {
            $run = DB::table('attendance_test_runs')->where('id', $id)->lockForUpdate()->first();
            abort_unless($run, 404);
            $m = json_decode($run->manifest, true);
            $employees = $m['employees'];
            $devices = $m['devices'];
            $imports = DB::table('attendance_imports')->whereIn('device_id', $devices)->pluck('id');
            if (DB::table('attendance_device_enrollments')->whereIn('device_id', $devices)->whereNotIn('employee_id', $employees)->exists()
                || DB::table('attendance_import_punches')->whereIn('import_id', $imports)->whereNotIn('employee_id', $employees)->exists()
                || DB::table('employee_data')->where('division', $m['division'])->whereNotIn('id', $employees)->exists()) {
                throw ValidationException::withMessages(['test' => ['This test session contains non-test assignments. Remove those assignments before cleanup.']]);
            }
            $accounts = DB::table('leave_accounts')->whereIn('employee_id', $employees)->pluck('id');
            $applications = DB::table('leave_applications')->whereIn('employee_id', $employees)->pluck('id');
            $ledger = DB::table('leave_ledger')->whereIn('account_id', $accounts)->pluck('id');
            $counts = ['employees' => count($employees), 'machines' => count($devices), 'imports' => $imports->count(), 'punches' => DB::table('attendance_logs')->whereIn('employee_id', $employees)->count(), 'days' => DB::table('attendance_days')->whereIn('employee_id', $employees)->count()];
            foreach (['employee_data' => $employees, 'attendance_devices' => $devices, 'attendance_imports' => $imports, 'leave_accounts' => $accounts, 'leave_applications' => $applications, 'leave_ledger' => $ledger] as $entity => $ids) {
                DB::table('audit_logs')->where('entity', $entity)->whereIn('entity_id', $ids)->delete();
            }
            DB::table('leave_ledger')->whereIn('account_id', $accounts)->delete();
            foreach (['leave_accounts', 'leave_applications', 'attendance_days', 'attendance_logs', 'attendance_import_punches', 'employee_profiles', 'attendance_device_enrollments'] as $table) {
                DB::table($table)->whereIn('employee_id', $employees)->delete();
            }
            DB::table('attendance_imports')->whereIn('id', $imports)->delete();
            DB::table('attendance_devices')->whereIn('id', $devices)->delete();
            DB::table('employee_data')->whereIn('id', $employees)->delete();
            DB::table('division')->where('id', $m['division'])->delete();
            DB::table('attendance_test_runs')->where('id', $id)->delete();
            app(WorkflowService::class)->audit('test_run_cleaned', 'attendance_test_runs', $id, $counts);

            return $counts;
        });
    }
}
