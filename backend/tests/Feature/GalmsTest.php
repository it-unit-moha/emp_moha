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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        Schema::dropIfExists('employee_data');
        foreach (['division', 'unit', 'designation'] as $table) {
            Schema::dropIfExists($table);
        }
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

    public function test_operations_require_validated_input_and_deductions_default_off(): void
    {
        $this->signIn();
        $this->getJson('/api/v1/attendance')->assertOk()->assertJsonPath('meta.implemented', true);
        $this->getJson('/api/v1/leaves')->assertOk()->assertJsonPath('meta.rules.automatic_deductions_enabled', false);
        $this->postJson('/api/v1/attendance/import')->assertUnprocessable()->assertJsonValidationErrors('file');
        $file = UploadedFile::fake()->createWithContent('device.csv', "unverified,device,format\n");
        $this->postJson('/api/v1/attendance/import', ['file' => $file])->assertUnprocessable();
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

    private function operationalEmployee(string $category = 'other'): int
    {
        $this->signIn(Role::Admin);

        return $this->postJson('/api/v1/employees', ['name' => 'Operational employee', 'enroll_id' => '001', 'service_grade' => 'III', 'staff_category' => $category, 'vacation_days' => 24])->assertCreated()->json('data.id');
    }

    public function test_enrollment_uniqueness_and_initial_accounts(): void
    {
        $employee = $this->operationalEmployee();
        $this->assertDatabaseHas('employee_profiles', ['employee_id' => $employee, 'enroll_id' => '001', 'staff_category' => 'other']);
        $this->assertDatabaseHas('leave_accounts', ['employee_id' => $employee, 'type' => 'casual', 'opening' => 21]);
        $this->postJson('/api/v1/employees', ['name' => 'Duplicate', 'enroll_id' => '001'])->assertUnprocessable()->assertJsonValidationErrors('enroll_id');
        $this->putJson('/api/v1/employees/'.$employee, ['name' => 'Edited', 'enroll_id' => '001', 'staff_category' => 'other'])->assertOk();
        $this->assertDatabaseCount('leave_accounts', 5);
    }

    public function test_import_deduplication_covering_and_idempotent_penalty_reconciliation(): void
    {
        $id = $this->operationalEmployee();
        config(['galms.leave.automatic_deductions_enabled' => true]);
        $csv = "enroll_id,timestamp,state\n";
        foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $date) {
            $csv .= "001,$date 08:45:00,IN\n001,$date 16:20:00,OUT\n";
        }
        $file = fn () => UploadedFile::fake()->createWithContent('punches.csv', $csv);
        $this->postJson('/api/v1/attendance/import', ['file' => $file()])->assertOk()->assertJsonPath('data.inserted', 6);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'date' => '2026-11-02', 'late_minutes' => 15, 'required_out' => '2026-11-02 16:30:00', 'status' => 'late_uncovered']);
        $this->assertDatabaseHas('leave_ledger', ['amount' => -0.5]);
        $this->postJson('/api/v1/attendance/import', ['file' => $file()])->assertOk()->assertJsonPath('data.inserted', 0);
        $this->assertDatabaseCount('attendance_logs', 6);
        $correction = UploadedFile::fake()->createWithContent('later.csv', "enroll_id,timestamp,state\n001,2026-11-02 17:00:00,OUT\n");
        $this->postJson('/api/v1/attendance/import', ['file' => $correction])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'date' => '2026-11-02', 'status' => 'late_covered']);
        $this->assertDatabaseHas('leave_ledger', ['amount' => 0.5]);
        $this->assertDatabaseCount('leave_ledger', 2);
    }

    public function test_invalid_import_is_atomic_missing_punches_are_reviewed_and_viewer_cannot_import(): void
    {
        $id = $this->operationalEmployee('pl');
        $bad = UploadedFile::fake()->createWithContent('invalid.csv', "enroll_id,timestamp,state\n001,2026-11-02 08:00:00,IN\nunknown,2026-11-02 16:45:00,OUT\n");
        $this->postJson('/api/v1/attendance/import', ['file' => $bad])->assertUnprocessable();
        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseCount('attendance_imports', 0);
        $one = UploadedFile::fake()->createWithContent('missing.csv', "enroll_id,timestamp,state\n001,2026-11-02 08:10:00,IN\n");
        $this->postJson('/api/v1/attendance/import', ['file' => $one])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'status' => 'review']);
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::create(['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => 'safe-test-password', 'role' => Role::Viewer]), 'web');
        $this->postJson('/api/v1/attendance/import', ['file' => $one])->assertForbidden();
        $this->postJson('/api/v1/holidays', ['date' => '2026-11-02', 'name' => 'Synthetic holiday', 'kind' => 'public'])->assertForbidden();
    }

    public function test_short_leave_third_request_and_half_day_casual_approval(): void
    {
        $id = $this->operationalEmployee();
        foreach (['2026-11-02', '2026-11-03'] as $date) {
            $this->postJson('/api/v1/leaves', ['employee_id' => $id, 'type' => 'short_morning', 'scope' => 'domestic', 'start_date' => $date, 'end_date' => $date, 'minutes' => 90, 'reason' => 'Test'])->assertCreated();
        }
        $payload = ['employee_id' => $id, 'type' => 'short_afternoon', 'scope' => 'domestic', 'start_date' => '2026-11-04', 'end_date' => '2026-11-04', 'minutes' => 30, 'reason' => 'Test'];
        $this->postJson('/api/v1/leaves', $payload)->assertUnprocessable()->assertJsonValidationErrors('short_leave_limit');
        $payload['type'] = 'casual';
        $payload['half_day'] = true;
        $request = $this->postJson('/api/v1/leaves', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/leaves/'.$request.'/review', ['status' => 'approved'])->assertOk();
        $this->assertDatabaseHas('leave_ledger', ['reference' => 'application:'.$request, 'amount' => -0.5]);
        $this->postJson('/api/v1/leaves/'.$request.'/review', ['status' => 'approved'])->assertUnprocessable();
        $this->assertDatabaseCount('leave_ledger', 1);
    }

    public function test_domestic_foreign_holidays_and_private_pdf_exports(): void
    {
        $id = $this->operationalEmployee();
        $this->postJson('/api/v1/holidays', ['date' => '2026-11-06', 'name' => 'Synthetic holiday', 'kind' => 'public'])->assertCreated();
        $base = ['employee_id' => $id, 'type' => 'vacation', 'scope' => 'domestic', 'start_date' => '2026-11-06', 'end_date' => '2026-11-09', 'reason' => 'Calendar test'];
        $domestic = $this->postJson('/api/v1/leaves', $base)->assertCreated()->json('data');
        $this->assertEquals(1, $domestic['days']);
        $this->postJson('/api/v1/leaves/'.$domestic['id'].'/review', ['status' => 'rejected'])->assertOk();
        $base['scope'] = 'foreign';
        $foreign = $this->postJson('/api/v1/leaves', $base)->assertCreated()->json('data');
        $this->assertEquals(4, $foreign['days']);
        foreach (['attendance', 'leave-utilization', 'late-warning'] as $type) {
            $response = $this->get('/api/v1/reports/pdf?type='.$type.'&month=2026-11');
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
        $this->getJson('/api/v1/audit')->assertOk();
    }

    public function test_admin_policy_switch_reconciles_existing_punches_and_preserves_ledger_history(): void
    {
        $id = $this->operationalEmployee();
        $csv = "enroll_id,timestamp,state\n";
        foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $date) {
            $csv .= "001,$date 08:45:00,IN\n001,$date 16:20:00,OUT\n";
        }
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('policy.csv', $csv)])->assertOk();
        $this->assertDatabaseCount('leave_ledger', 0);
        $this->postJson('/api/v1/settings/penalties', ['enabled' => true, 'confirm_rules' => false])->assertUnprocessable();
        $this->postJson('/api/v1/settings/penalties', ['enabled' => true, 'confirm_rules' => true])->assertOk();
        $this->assertDatabaseHas('leave_ledger', ['amount' => -0.5]);
        $this->postJson('/api/v1/settings/penalties', ['enabled' => true, 'confirm_rules' => true])->assertOk();
        $this->assertDatabaseCount('leave_ledger', 1);
        $this->postJson('/api/v1/settings/penalties', ['enabled' => false, 'confirm_rules' => true])->assertOk();
        $this->assertDatabaseHas('leave_ledger', ['amount' => 0.5]);
        $this->assertDatabaseCount('leave_ledger', 2);
        Storage::fake('local');
        $this->artisan('galms:late-warning', ['--month' => '2026-11'])->assertExitCode(0);
        Storage::disk('local')->assertExists('reports/late-warning-2026-11.pdf');
        $snapshot = DB::table('report_runs')->first();
        $this->get('/api/v1/reports/snapshots/'.$snapshot->id)->assertOk();
    }

    public function test_account_management_prevents_self_lockout_and_disabled_login(): void
    {
        $admin = $this->signIn(Role::Admin);
        $this->putJson('/api/v1/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'Authorized Viewer', 'is_active' => false])->assertUnprocessable();
        $id = $this->postJson('/api/v1/users', ['name' => 'New viewer', 'email' => 'newviewer@example.test', 'password' => 'Long-test-password', 'role' => 'Authorized Viewer', 'is_active' => true])->assertCreated()->assertJsonMissingPath('data.password')->json('data.id');
        $this->putJson('/api/v1/users/'.$id, ['name' => 'New viewer', 'email' => 'newviewer@example.test', 'role' => 'Authorized Viewer', 'is_active' => false])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $id, 'is_active' => 0]);
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->postJson('/api/v1/login', ['email' => 'newviewer@example.test', 'password' => 'Long-test-password'])->assertUnprocessable();
    }

    public function test_leave_cancellation_compensates_ledger_and_allows_calendar_correction(): void
    {
        $id = $this->operationalEmployee();
        $request = $this->postJson('/api/v1/leaves', ['employee_id' => $id, 'type' => 'casual', 'scope' => 'domestic', 'start_date' => '2026-11-06', 'end_date' => '2026-11-06', 'half_day' => true, 'reason' => 'Test cancellation'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/leaves/'.$request.'/review', ['status' => 'approved'])->assertOk();
        $holiday = ['date' => '2026-11-06', 'name' => 'Test holiday', 'kind' => 'public'];
        $this->postJson('/api/v1/holidays', $holiday)->assertUnprocessable();
        $this->postJson('/api/v1/leaves/'.$request.'/review', ['status' => 'cancelled'])->assertOk();
        $this->assertDatabaseHas('leave_ledger', ['reference' => 'cancellation:'.$request, 'amount' => 0.5]);
        $this->postJson('/api/v1/leaves/'.$request.'/review', ['status' => 'cancelled'])->assertUnprocessable();
        $this->postJson('/api/v1/holidays', $holiday)->assertCreated();
    }
}
