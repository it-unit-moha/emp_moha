<?php

use App\Enums\Role;
use App\Http\Resources\EmployeeResource;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('galms:create-admin', function () {
    $name = $this->ask('Administrator name');
    $email = $this->ask('Email');
    $password = $this->secret('Password (at least 12 characters)');
    if (! is_string($name) || ! strlen(trim($name)) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 12) {
        $this->error('Valid name, email and a password of at least 12 characters are required.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('An account with that email already exists.');

        return 1;
    }
    User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => Role::SuperAdmin]);
    $this->info('Administrator created.');
})->purpose('Provision a local administrator without default credentials');

Artisan::command('galms:check-database', function () {
    $this->table(['Register', 'Records'], [
        ['Employees', Employee::count()], ['Divisions', Division::count()], ['Units', Unit::count()], ['Designations', Designation::count()],
    ]);
    $employee = Employee::with(['divisionRecord', 'unitRecord', 'designationRecord'])->first();
    if ($employee) {
        (new EmployeeResource($employee))->resolve();
    }
    $this->info('Database connection, imported models, relationships and employee resource verified. No records were written.');
})->purpose('Read-only verification of imported register integration');

Artisan::command('galms:late-warning {--month=}', function () {
    $month = $this->option('month') ?: now('Asia/Colombo')->format('Y-m');
    if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $this->error('Use YYYY-MM.');

        return 1;
    }
    $path = 'reports/late-warning-'.$month.'.pdf';
    $pdf = app(ReportService::class)->pdf('late-warning', $month);
    Storage::disk('local')->put($path, $pdf->output());
    DB::table('report_runs')->updateOrInsert(['month' => $month, 'type' => 'late-warning'], ['path' => $path, 'created_at' => now(), 'updated_at' => now()]);
    $this->info('Private late-warning snapshot generated: '.$month);
})->purpose('Generate the monthly late-warning PDF');

Schedule::command('galms:late-warning')->monthlyOn(25, '09:00')->timezone('Asia/Colombo')->withoutOverlapping();
