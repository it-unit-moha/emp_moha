<?php

use App\Enums\Role;
use App\Http\Resources\EmployeeResource;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

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
