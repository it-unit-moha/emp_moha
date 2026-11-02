<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Services\AttendanceRulesService;
use App\Services\LeaveRulesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GalmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // A hard guard prevents tests from ever touching employee_info, even with cached config.
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require isolated in-memory SQLite.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        foreach (['division' => 'division', 'unit' => 'unit_name', 'designation' => 'designation'] as $table => $column) {
            Schema::create($table, function (Blueprint $t) use ($column, $table) {
                $t->increments('id');
                $t->string($column)->nullable();
                if ($table === 'division') {
                    $t->integer('status')->default(1);
                }
            });
        }
        Schema::create('employee_data', function (Blueprint $t) {
            $t->increments('id');
            foreach (['name', 'nic', 'emp_no', 'email', 'address', 'contact_no', 'gender', 'unit'] as $column) {
                $t->string($column)->nullable();
            }$t->unique('nic');
            $t->date('dob')->nullable();
            $t->integer('division')->nullable();
            $t->integer('designation')->nullable();
            $t->integer('is_active')->nullable();
            $t->timestamp('inserted_on')->useCurrent();
            $t->dateTime('updated_on')->nullable();
        });
    }

    private function signIn(Role $role = Role::Admin): User
    {
        $u = User::create(['name' => 'Test officer', 'email' => 'officer@example.test', 'password' => 'a-secure-test-password', 'role' => $role]);
        $this->actingAs($u, 'web');

        return $u;
    }

    public function test_guest_cannot_read_private_records(): void
    {
        $this->getJson('/api/v1/employees')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_session_login_me_and_logout(): void
    {
        User::create(['name' => 'Officer', 'email' => 'officer@example.test', 'password' => 'a-secure-test-password', 'role' => Role::HR]);
        $this->postJson('/api/v1/login', ['email' => 'officer@example.test', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/login', ['email' => 'officer@example.test', 'password' => 'a-secure-test-password'])->assertOk()->assertJsonPath('data.role', 'HR Officer')->assertJsonMissingPath('data.password');
        $this->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.email', 'officer@example.test');
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_master_crud_and_reference_protection(): void
    {
        $this->signIn();
        foreach (['divisions', 'units', 'designations'] as $module) {
            $created = $this->postJson('/api/v1/'.$module, ['name' => 'Test record'])->assertCreated()->json('data.id');
            $this->getJson('/api/v1/'.$module.'/'.$created)->assertOk()->assertJsonPath('data.name', 'Test record');
            $this->putJson('/api/v1/'.$module.'/'.$created, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
            $column = match ($module) {
                'divisions' => 'division','units' => 'unit','designations' => 'designation'
            };
            $employee = Employee::create(['name' => 'Assigned', $column => $created]);
            $this->deleteJson('/api/v1/'.$module.'/'.$created)->assertUnprocessable();
            $employee->update([$column => null]);
            $this->deleteJson('/api/v1/'.$module.'/'.$created)->assertOk();
        }
    }

    public function test_employee_crud_search_filters_sort_and_archive(): void
    {
        $this->signIn();
        $division = Division::create(['division' => 'Administration']);
        $unit = Unit::create(['unit_name' => 'Registry']);
        $designation = Designation::create(['designation' => 'Officer']);
        $payload = ['name' => 'Zelda Officer', 'nic' => '123456789V', 'emp_no' => 'E-100', 'division' => $division->id, 'unit' => (string) $unit->id, 'designation' => $designation->id];
        $id = $this->postJson('/api/v1/employees', $payload)->assertCreated()->assertJsonPath('data.is_active', 1)->json('data.id');
        $this->getJson('/api/v1/employees/'.$id)->assertOk()->assertJsonPath('data.unit_name', 'Registry');
        $payload['name'] = 'Amended Officer';
        $this->putJson('/api/v1/employees/'.$id, $payload)->assertOk()->assertJsonPath('data.name', 'Amended Officer');
        $this->getJson('/api/v1/employees?search=Amended&division='.$division->id.'&sort=name&direction=desc&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson('/api/v1/employees', $payload)->assertUnprocessable()->assertJsonValidationErrors('nic');
        $this->deleteJson('/api/v1/employees/'.$id)->assertOk();
        $this->assertDatabaseHas('employee_data', ['id' => $id, 'is_active' => 0]);
        $this->getJson('/api/v1/employees?status=archived')->assertJsonPath('meta.total', 1);
    }

    public function test_invalid_assignments_and_sort_are_rejected(): void
    {
        $this->signIn();
        $this->postJson('/api/v1/employees', ['name' => 'Test', 'unit' => '9999'])->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->getJson('/api/v1/employees?sort=password')->assertUnprocessable();
        $this->postJson('/api/v1/divisions', ['name' => ''])->assertUnprocessable();
    }

    public function test_viewer_cannot_mutate_and_hr_cannot_delete_master_data(): void
    {
        $this->signIn(Role::Viewer);
        $division = Division::create(['division' => 'Test']);
        $this->getJson('/api/v1/employees')->assertOk();
        $this->postJson('/api/v1/employees', ['name' => 'No'])->assertForbidden();
        $this->putJson('/api/v1/divisions/'.$division->id, ['name' => 'No'])->assertForbidden();
        $this->deleteJson('/api/v1/divisions/'.$division->id)->assertForbidden();
        $this->getJson('/api/v1/user')->assertJsonMissingPath('data.password');
    }

    public function test_hr_can_create_but_cannot_delete_master_data(): void
    {
        $division = Division::create(['division' => 'Test']);
        $hr = User::create(['name' => 'HR', 'email' => 'hr@example.test', 'password' => 'a-secure-test-password', 'role' => Role::HR]);
        $this->actingAs($hr, 'web');
        $this->postJson('/api/v1/units', ['name' => 'New unit'])->assertCreated();
        $this->deleteJson('/api/v1/divisions/'.$division->id)->assertForbidden();
    }

    public function test_dashboard_has_real_counts_and_no_fabricated_attendance(): void
    {
        $this->signIn();
        Employee::create(['name' => 'One']);
        Division::create(['division' => 'One']);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.total_employees', 1)->assertJsonPath('data.total_divisions', 1)->assertJsonPath('data.total_units', 0)->assertJsonPath('data.present', null)->assertJsonPath('data.recent_attendance', []);
    }

    public function test_foundations_do_not_enable_unapproved_operations(): void
    {
        $this->signIn();
        $this->getJson('/api/v1/attendance')->assertOk()->assertJsonPath('meta.implemented', false);
        $this->getJson('/api/v1/leaves')->assertOk()->assertJsonPath('meta.rules.automatic_deductions_enabled', false);
        $this->postJson('/api/v1/attendance/import')->assertUnprocessable()->assertJsonValidationErrors('file');
        $file = UploadedFile::fake()->createWithContent('device.csv', "unverified,device,format\n");
        $this->postJson('/api/v1/attendance/import', ['file' => $file])->assertConflict();
        $this->postJson('/api/v1/register', [])->assertNotFound();
    }

    public function test_passwords_are_hashed_and_leave_limits_are_configurable(): void
    {
        $u = $this->signIn();
        $this->assertTrue(Hash::check('a-secure-test-password', $u->password));
        $service = app(LeaveRulesService::class);
        $this->assertTrue($service->validateShortLeave(90, 1));
        $this->assertFalse($service->validateShortLeave(91, 0));
        $this->assertFalse($service->validateShortLeave(30, 2));
        $this->assertSame('08:00', app(AttendanceRulesService::class)->schedule('pl')['start']);
        $this->expectException(\DomainException::class);
        $service->applyLatePenalty();
    }
}
