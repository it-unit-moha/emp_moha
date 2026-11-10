<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Tests\TestCase;

class FreshInstallTest extends TestCase
{
    public function test_empty_database_migrates_into_a_self_contained_employee_register(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Fresh-install verification requires isolated in-memory SQLite.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->actingAs(User::create(['name' => 'Install admin', 'email' => 'install@example.test', 'password' => 'Long-test-password', 'role' => Role::Admin]), 'web');
        $unit = $this->postJson('/api/v1/units', ['name' => 'Fresh unit'])->assertCreated()->json('data.id');
        $employee = $this->postJson('/api/v1/employees', ['name' => 'Fresh employee', 'unit' => (string) $unit, 'staff_category' => 'pl', 'enroll_id' => 'FRESH001', 'service_grade' => 'II'])->assertCreated();
        $employee->assertJsonPath('data.unit', (string) $unit)->assertJsonPath('data.staff_category', 'pl');
        $this->assertDatabaseCount('leave_accounts', 5);
        $this->getJson('/api/v1/workflow')->assertOk()->assertJsonPath('data.unmapped', 0);
    }
}
