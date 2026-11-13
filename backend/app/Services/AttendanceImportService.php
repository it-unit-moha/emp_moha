<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceImportService
{
    private function fail(string $filename, int $line, string $message): never
    {
        throw ValidationException::withMessages(['files' => ["$filename, line $line: $message No files from this upload were saved."]]);
    }

    public function parse(string $path, string $filename, ?object $device): array
    {
        $handle = fopen($path, 'rb');
        $rows = [];
        $cache = [];      // enroll_id => employee_id  (resolved and valid employees)
        $skipped = [];    // enroll_id => true          (not registered here — skip quietly)
        $format = $device?->format;
        $inCode = $device?->in_code ?? 'IN';
        $outCode = $device?->out_code ?? 'OUT';
        $line = 0;
        $delimiter = null;
        try {
            while (($text = fgets($handle)) !== false) {
                $line++;
                $text = trim($text, "\r\n");
                if ($line === 1) {
                    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
                }
                if (trim($text) === '') {
                    continue;
                }
                if (str_contains($text, "\0")) {
                    $this->fail($filename, $line, 'Use UTF-8 text; this file appears to use another encoding.');
                }
                if ($format === null) {
                    // Auto-detect: six-column device export vs. headed CSV/delimited.
                    $format = preg_match('/^\s*\S+\s+\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+\d+\s+\d+\s+\d+\s+\d+\s*$/', $text)
                        ? 'device_text' : 'delimited';
                }
                if ($format === 'delimited') {
                    if ($delimiter === null) {
                        foreach ([',', "\t", ';'] as $candidate) {
                            if (array_map('trim', str_getcsv($text, $candidate, '"', '')) === ['enroll_id', 'timestamp', 'state']) {
                                $delimiter = $candidate;
                                break;
                            }
                        }
                        if ($delimiter === null) {
                            $this->fail($filename, $line, 'Expected an attendance export row or the header enroll_id,timestamp,state (comma, tab or semicolon).');
                        }

                        continue;
                    }
                    $parts = array_map('trim', str_getcsv($text, $delimiter, '"', ''));
                    if (count($parts) !== 3) {
                        $this->fail($filename, $line, 'Expected exactly enrollment ID, timestamp and state.');
                    }
                    [$enroll, $timestamp, $code] = $parts;
                } else {
                    // Six-column device export: ID  YYYY-MM-DD HH:mm:ss  VERIFY  STATE  WORKCODE  RESERVED
                    if (! preg_match('/^\s*(\S+)\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*$/', $text, $parts)) {
                        $this->fail($filename, $line, 'Expected ID YYYY-MM-DD HH:mm:ss VERIFY STATE WORKCODE RESERVED.');
                    }
                    [$all, $enroll, $timestamp, $verify, $code] = $parts;
                }
                if (count($rows) >= 50000) {
                    $this->fail($filename, $line, 'Maximum 50,000 punches per file.');
                }
                if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $enroll)) {
                    // Silently skip malformed IDs — do not abort the whole file.
                    continue;
                }
                // 255 is an undirected scan from ZKTeco-style devices — treat as RAW.
                $state = ! $device && $format === 'device_text' && $code === '255'
                    ? 'RAW'
                    : ($code === $inCode ? 'IN' : ($code === $outCode ? 'OUT' : null));
                if (! $state) {
                    // Unknown state code — skip this punch, do not abort.
                    continue;
                }
                try {
                    $time = Date::createFromFormat('!Y-m-d H:i:s', $timestamp, 'Asia/Colombo');
                } catch (\Throwable) {
                    $time = null;
                }
                if (! $time || $time->format('Y-m-d H:i:s') !== $timestamp) {
                    $this->fail($filename, $line, 'Invalid timestamp; use YYYY-MM-DD HH:mm:ss in Asia/Colombo time.');
                }
                // Skip enrollment IDs that were already found to be unmapped.
                if (isset($skipped[$enroll])) {
                    continue;
                }
                if (! array_key_exists($enroll, $cache)) {
                    // Resolve enrollment ID → employee.
                    // First try device-specific mapping, then fall back to emp_no or enroll_id on employee_data.
                    $employeeId = $device
                        ? DB::table('attendance_device_enrollments')->where(['device_id' => $device->id, 'enroll_id' => $enroll])->value('employee_id')
                        : null;
                    if (! $employeeId && (! $device || $device->shared_enrollment)) {
                        // Look up by enroll_id column first, then by emp_no (which is the machine ID in this system)
                        $employeeId = DB::table('employee_data')
                            ->where('enroll_id', $enroll)
                            ->where('is_active', 1)
                            ->value('id');
                        if (! $employeeId) {
                            $employeeId = DB::table('employee_data')
                                ->where('emp_no', $enroll)
                                ->where('is_active', 1)
                                ->value('id');
                        }
                    }
                    $employee = $employeeId ? Employee::find($employeeId) : null;
                    if (! $employee || $employee->is_active !== 1) {
                        // Not registered in this system — skip all punches for this ID silently.
                        $skipped[$enroll] = true;
                        continue;
                    }
                    // If no staff_category set yet, still accept the punch — it will be evaluated
                    // once the category is assigned. Use a default fallback for calculation.
                    $cache[$enroll] = $employee->id;
                }
                $rows[] = ['employee_id' => $cache[$enroll], 'enroll_id' => $enroll, 'punched_at' => $timestamp, 'state' => $state];
            }
        } finally {
            fclose($handle);
        }
        if (! $rows) {
            // Build a helpful message listing the unrecognised IDs.
            $unknownList = array_keys($skipped);
            if ($unknownList) {
                $sample = implode(', ', array_slice($unknownList, 0, 10));
                $more = count($unknownList) > 10 ? ' … and ' . (count($unknownList) - 10) . ' more.' : '';
                $this->fail($filename, $line,
                    'None of the enrollment IDs in this file are registered in the system. ' .
                    'Open each employee record, go to the Attendance tab, and enter their machine enrollment ID exactly as it appears in the file. ' .
                    "IDs found in file: $sample$more"
                );
            }
            $this->fail($filename, $line, 'The file has no attendance punches.');
        }

        return $rows;
    }

    public function importMany(array $files): array
    {
        // Parse every file before writing; one fatal format error rejects the whole batch.
        $prepared = [];
        foreach ($files as $file) {
            $device = empty($file['device_id']) ? null : DB::table('attendance_devices')->find($file['device_id']);
            if (! empty($file['device_id']) && ! $device) {
                $this->fail($file['filename'], 0, 'Machine no longer exists.');
            }
            $hash = hash_file('sha256', $file['path']);
            $source = hash('sha256', ($device?->id ?? 0).'|'.$hash);
            $previous = DB::table('attendance_imports')->where('source_key', $source)->first();
            $rows = $previous ? [] : $this->parse($file['path'], $file['filename'], $device);
            $prepared[] = [...$file, 'device' => $device, 'hash' => $hash, 'source' => $source, 'rows' => $rows, 'previous' => $previous];
        }

        return DB::transaction(function () use ($prepared) {
            $workflow = app(WorkflowService::class);
            $ids = collect($prepared)->flatMap(fn ($f) => array_column($f['rows'], 'employee_id'))->unique()->sort()->values()->all();
            // Verify all employees are still active (no separate profiles table in real DB)
            if (Employee::whereIn('id', $ids)->where('is_active', 1)->count() !== count($ids)) {
                throw ValidationException::withMessages(['files' => ['An employee changed during upload. Refresh and retry.']]);
            }
            $results = [];
            $days = [];
            $months = [];
            foreach ($prepared as $file) {
                $created = DB::table('attendance_imports')->insertOrIgnore(['filename' => basename($file['filename']), 'sha256' => $file['hash'], 'source_key' => $file['source'], 'device_id' => $file['device']?->id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
                $batch = DB::table('attendance_imports')->where('source_key', $file['source'])->lockForUpdate()->first();
                if (! $created) {
                    $results[] = ['id' => $batch->id, 'filename' => $file['filename'], 'device_id' => $batch->device_id, 'inserted' => 0, 'duplicates' => $batch->inserted + $batch->duplicates, 'repeated_file' => true];

                    continue;
                }
                $inserted = 0;
                foreach ($file['rows'] as $row) {
                    $punch = array_diff_key($row, ['enroll_id' => true]);
                    $inserted += DB::table('attendance_logs')->insertOrIgnore([...$punch, 'import_id' => $batch->id]);
                    DB::table('attendance_import_punches')->insertOrIgnore([...$row, 'import_id' => $batch->id]);
                    $date = substr($row['punched_at'], 0, 10);
                    $days[$row['employee_id'].'|'.$date] = [$row['employee_id'], $date];
                    $months[$row['employee_id'].'|'.substr($date, 0, 7)] = [$row['employee_id'], substr($date, 0, 7)];
                }
                $result = ['id' => $batch->id, 'filename' => $file['filename'], 'device_id' => $batch->device_id, 'inserted' => $inserted, 'duplicates' => count($file['rows']) - $inserted, 'repeated_file' => false];
                DB::table('attendance_imports')->where('id', $batch->id)->update(['inserted' => $result['inserted'], 'duplicates' => $result['duplicates']]);
                $workflow->audit('import', 'attendance_imports', $batch->id, $result);
                $results[] = $result;
            }
            foreach ($days as [$employee, $date]) {
                $workflow->calculate($employee, $date);
            }
            foreach ($months as [$employee, $month]) {
                $workflow->reconcilePenalty($employee, $month);
            }

            return ['inserted' => array_sum(array_column($results, 'inserted')), 'duplicates' => array_sum(array_column($results, 'duplicates')), 'files' => $results, 'evaluated_days' => count($days), 'evaluated_months' => count($months)];
        });
    }
}
