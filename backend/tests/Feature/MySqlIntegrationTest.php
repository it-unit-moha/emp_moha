<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlIntegrationTest extends TestCase
{
    public function test_crud_against_isolated_copy_of_imported_mysql_definitions(): void
    {
        if (getenv('GALMS_VERIFY_MYSQL') !== '1') {
            $this->markTestSkipped('Opt in with GALMS_VERIFY_MYSQL=1. Creates and retains a separate galms_test_* database.');
        }
        config(['database.connections.mysql.database' => 'employee_info']);
        DB::purge('mysql');
        $source = DB::connection('mysql');
        $definitions = [];
        foreach (['employee_data', 'division', 'unit', 'designation'] as $table) {
            $definition = (array) $source->selectOne('SHOW CREATE TABLE `'.$table.'`');
            $definitions[] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $definition['Create Table']);
        }
        $database = 'galms_test_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
        $source->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database]);
        DB::purge('mysql');
        $this->assertStringStartsWith('galms_test_', DB::connection()->getDatabaseName());
        foreach ($definitions as $definition) {
            DB::statement($definition);
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $admin = User::create(['name' => 'Verification admin', 'email' => 'verification@example.test', 'password' => bin2hex(random_bytes(24)), 'role' => Role::SuperAdmin]);
        $this->actingAs($admin, 'web');
        $division = $this->postJson('/api/v1/divisions', ['name' => 'Verification division'])->assertCreated()->json('data.id');
        $unit = $this->postJson('/api/v1/units', ['name' => 'Verification unit'])->assertCreated()->json('data.id');
        $designation = $this->postJson('/api/v1/designations', ['name' => 'Verification designation'])->assertCreated()->json('data.id');
        $payload = ['name' => 'Synthetic verification employee', 'nic' => 'TEST000001', 'unit' => (string) $unit, 'division' => $division, 'designation' => $designation];
        $id = $this->postJson('/api/v1/employees', $payload)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/employees/'.$id)->assertOk()->assertJsonPath('data.division_name', 'Verification division');
        $payload['name'] = 'Updated verification employee';
        $this->putJson('/api/v1/employees/'.$id, $payload)->assertOk()->assertJsonPath('data.name', $payload['name']);
        foreach (['divisions' => $division, 'units' => $unit, 'designations' => $designation] as $module => $key) {
            $this->putJson('/api/v1/'.$module.'/'.$key, ['name' => 'Updated '.$module])->assertOk();
            $this->deleteJson('/api/v1/'.$module.'/'.$key)->assertUnprocessable();
        }
        $this->deleteJson('/api/v1/employees/'.$id)->assertOk();
        $this->assertDatabaseHas('employee_data', ['id' => $id, 'is_active' => 0]);
        $this->getJson('/api/v1/employees?status=archived&search=Updated&sort=name')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.total_employees', 1);
        $payload['division'] = null;
        $payload['unit'] = null;
        $payload['designation'] = null;
        $this->putJson('/api/v1/employees/'.$id, $payload)->assertOk();
        foreach (['divisions' => $division, 'units' => $unit, 'designations' => $designation] as $module => $key) {
            $this->deleteJson('/api/v1/'.$module.'/'.$key)->assertOk();
        }
        fwrite(STDOUT,"\nRetained isolated verification database: ".$database."\n");
    }
}
