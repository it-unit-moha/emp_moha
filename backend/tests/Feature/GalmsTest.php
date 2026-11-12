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

    public function test_employee_legacy_fields_uploads_and_status_can_be_created_and_updated(): void
    {
        Storage::fake('local');
        $this->signIn();
        $designation = Designation::create(['designation' => 'Investigation Assistant']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $payload = [
            'name' => 'Test Initials', 'emp_no' => '300052', 'nic' => '747412600V',
            'address' => 'Test address', 'contact_no' => '0112650228', 'email' => 'employee@example.test',
            'dob' => '1974-08-28', 'gender' => 'Female', 'designation' => $designation->id,
            'date_of_issue' => '2021-01-05', 'date_of_expiry' => '2024-01-05', 'is_active' => 0,
            'photo' => UploadedFile::fake()->createWithContent('photo.png', $png),
            'signature' => UploadedFile::fake()->createWithContent('signature.png', $png),
            'application_form' => UploadedFile::fake()->createWithContent('application.pdf', "%PDF-1.4\nTest application\n%%EOF"),
        ];
        $id = $this->post('/api/v1/employees', $payload, ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.date_of_issue', '2021-01-05')->assertJsonPath('data.date_of_expiry', '2024-01-05')
            ->assertJsonPath('data.is_active', 0)->assertJsonPath('data.has_photo', true)
            ->assertJsonPath('data.has_signature', true)->assertJsonPath('data.has_application_form', true)->json('data.id');
        $profile = DB::table('employee_profiles')->where('employee_id', $id)->first();
        foreach (['photo', 'signature', 'application_form'] as $kind) {
            Storage::disk('local')->assertExists($profile->{$kind.'_path'});
            $this->get('/api/v1/employees/'.$id.'/files/'.$kind)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        // Multipart updates work with PHP method override, and omitted files are retained.
        $this->post('/api/v1/employees/'.$id, ['_method' => 'PUT', 'name' => 'Updated Initials', 'is_active' => '1'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.is_active', 1)->assertJsonPath('data.has_photo', true)
            ->assertJsonPath('data.date_of_expiry', '2024-01-05');
        $next = Designation::create(['designation' => 'Administrative Officer']);
        $this->putJson('/api/v1/employees/'.$id, ['name' => 'Updated Initials', 'designation' => $next->id])->assertOk()->assertJsonPath('data.designation_name', 'Administrative Officer');
        $this->assertDatabaseHas('designation', ['id' => $designation->id, 'designation' => 'Investigation Assistant']);
        $this->post('/api/v1/employees/'.$id, ['_method' => 'PUT', 'name' => 'Updated Initials', 'photo' => UploadedFile::fake()->createWithContent('replacement.png', $png)], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($profile->photo_path);
        Storage::disk('local')->assertExists(DB::table('employee_profiles')->where('employee_id', $id)->value('photo_path'));
        Storage::disk('local')->assertExists($profile->signature_path);
    }

    public function test_employee_attachments_dates_and_permissions_are_validated(): void
    {
        Storage::fake('local');
        $this->signIn();
        $this->postJson('/api/v1/employees', ['name' => 'Invalid dates', 'date_of_issue' => '2026-11-12', 'date_of_expiry' => '2025-11-12'])->assertUnprocessable()->assertJsonValidationErrors('date_of_expiry');
        $this->postJson('/api/v1/employees', ['name' => 'Invalid status', 'is_active' => 2])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->post('/api/v1/employees', ['name' => 'Invalid attachment', 'photo' => UploadedFile::fake()->createWithContent('bad.png', '<script>not an image</script>')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->post('/api/v1/employees', ['name' => 'Oversized attachment', 'application_form' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('application_form');
        $this->assertDatabaseCount('employee_data', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $employee = Employee::create(['name' => 'Existing record']);
        $this->getJson('/api/v1/employees/'.$employee->id.'/files/photo')->assertNotFound();
        $viewer = User::first();
        $viewer->update(['role' => Role::Viewer]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($viewer->refresh(), 'web');
        $this->putJson('/api/v1/employees/'.$employee->id, ['name' => 'Forbidden', 'is_active' => 0])->assertForbidden();
        auth('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/employees/'.$employee->id.'/files/photo')->assertUnauthorized();
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

    public function test_eight_machine_temporary_session_combines_punches_and_cleans_up_owned_records(): void
    {
        $real = $this->operationalEmployee();
        config(['galms.leave.automatic_deductions_enabled' => true]);
        $run = $this->postJson('/api/v1/attendance/test-runs')->assertCreated()->json('data');
        $this->assertCount(8, $run['fixtures']);
        $this->assertDatabaseCount('employee_data', 3);
        $result = $this->postJson('/api/v1/attendance/test-runs/'.$run['id'].'/import')->assertOk()->json('data');
        $this->assertEquals(15, $result['inserted']);
        $this->assertEquals(5, $result['duplicates']);
        $this->assertEquals(8, $result['evaluated_days']);
        $this->assertDatabaseCount('attendance_imports', 8);
        $this->assertDatabaseCount('attendance_import_punches', 20);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $run['employee_ids'][0], 'date' => '2026-11-02', 'actual_in' => '2026-11-02 08:45:00', 'actual_out' => '2026-11-02 16:30:00', 'evaluation' => 'late_covered']);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $run['employee_ids'][0], 'date' => '2026-11-06', 'evaluation' => 'half_day']);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $run['employee_ids'][1], 'date' => '2026-11-04', 'evaluation' => 'review']);
        $this->assertDatabaseHas('leave_ledger', ['amount' => -0.5]);
        $this->postJson('/api/v1/attendance/test-runs/'.$run['id'].'/import')->assertOk()->assertJsonPath('data.inserted', 0)->assertJsonPath('data.duplicates', 20);
        $this->assertDatabaseCount('attendance_logs', 15);
        $this->assertDatabaseCount('leave_ledger', 1);
        $this->get('/api/v1/reports/pdf?type=divisional&month=2026-11&division_id='.$run['division_id'])->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->deleteJson('/api/v1/attendance/test-runs/'.$run['id'])->assertOk()->assertJsonPath('deleted.punches', 15);
        foreach (['attendance_test_runs', 'attendance_devices', 'attendance_device_enrollments', 'attendance_imports', 'attendance_import_punches', 'attendance_days', 'attendance_logs', 'leave_ledger', 'division'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('employee_data', 1);
        $this->assertDatabaseHas('employee_data', ['id' => $real]);
        $this->assertDatabaseCount('employee_profiles', 1);
        $this->assertDatabaseCount('leave_accounts', 5);
        $this->getJson('/api/v1/attendance/test-runs')->assertOk()->assertJsonCount(0, 'runs');
    }

    public function test_multi_file_batch_is_atomic_and_reports_invalid_filename_and_line(): void
    {
        $this->operationalEmployee();
        $good = UploadedFile::fake()->createWithContent('gate-one.txt', "enroll_id\ttimestamp\tstate\n001\t2026-11-02 08:30:00\tIN\n");
        $bad = UploadedFile::fake()->createWithContent('gate-two.txt', "enroll_id,timestamp,state\n001,2026-11-02 16:15:00,UNRECOGNIZED\n");
        $r = $this->postJson('/api/v1/attendance/import', ['files' => [$good, $bad]])->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->assertStringContainsString('gate-two.txt, line 2', $r->json('errors.files.0'));
        $this->assertDatabaseCount('attendance_imports', 0);
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_attlog_exports_combine_without_a_machine_and_keep_single_scans_for_review(): void
    {
        $id = $this->operationalEmployee();
        DB::table('employee_profiles')->where('employee_id', $id)->update(['enroll_id' => '301110']);
        $morning = "   301110\t2026-10-01 07:13:00\t1\t255\t1\t0\n";
        $evening = "   301110\t2026-10-01 16:30:23\t1\t255\t1\t0\n";
        $single = "   301110\t2026-10-02 16:30:00\t1\t255\t1\t0\n";
        $files = fn () => [UploadedFile::fake()->createWithContent('3929151505715_attlog.dat', $evening.$morning), UploadedFile::fake()->createWithContent('3929151505714_attlog.dat', $morning.$single)];
        $this->postJson('/api/v1/attendance/import', ['files' => $files()])->assertOk()->assertJsonPath('data.inserted', 3)->assertJsonPath('data.duplicates', 1);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'date' => '2026-10-01', 'actual_in' => '2026-10-01 07:13:00', 'actual_out' => '2026-10-01 16:30:23', 'evaluation' => 'on_time']);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'date' => '2026-10-02', 'actual_out' => null, 'evaluation' => 'review']);
        $this->assertDatabaseHas('attendance_imports', ['device_id' => null]);
        $this->assertDatabaseCount('attendance_devices', 0);
        $this->postJson('/api/v1/attendance/import', ['files' => $files()])->assertOk()->assertJsonPath('data.inserted', 0);
        // An earlier scan arriving in a subsequent upload must update the full day.
        $earlier = UploadedFile::fake()->createWithContent('earlier.txt', "301110\t2026-10-02 08:30:00\t1\t255\t1\t0\n");
        $this->postJson('/api/v1/attendance/import', ['files' => [$earlier]])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'date' => '2026-10-02', 'actual_in' => '2026-10-02 08:30:00', 'actual_out' => '2026-10-02 16:30:00', 'evaluation' => 'on_time']);
    }

    public function test_attlog_unknown_enrollment_or_state_rejects_the_batch(): void
    {
        $this->operationalEmployee();
        $good = fn () => UploadedFile::fake()->createWithContent('good.dat', "001\t2026-10-01 08:30:00\t1\t255\t1\t0\n");
        foreach (["999\t2026-10-01 08:30:00\t1\t255\t1\t0\n", "001\t2026-10-01 08:30:00\t1\t4\t1\t0\n", "001\t2026-02-30 08:30:00\t1\t255\t1\t0\n"] as $invalid) {
            $this->postJson('/api/v1/attendance/import', ['files' => [$good(), UploadedFile::fake()->createWithContent('invalid.dat', $invalid)]])->assertUnprocessable()->assertJsonValidationErrors('files');
            $this->assertDatabaseCount('attendance_logs', 0);
            $this->assertDatabaseCount('attendance_imports', 0);
        }
    }

    public function test_same_file_content_from_different_machines_retains_both_sources_without_duplicate_punches(): void
    {
        $id = $this->operationalEmployee();
        $devices = [];
        foreach (['Entrance', 'Office'] as $name) {
            $devices[] = $this->postJson('/api/v1/attendance/devices', ['name' => $name, 'format' => 'delimited', 'in_code' => 'IN', 'out_code' => 'OUT', 'shared_enrollment' => true])->assertCreated()->json('data.id');
        }
        $csv = "enroll_id;timestamp;state\n001;2026-11-02 08:30:00;IN\n001;2026-11-02 16:15:00;OUT\n";
        $this->postJson('/api/v1/attendance/import', ['files' => [UploadedFile::fake()->createWithContent('a.txt', $csv), UploadedFile::fake()->createWithContent('b.txt', $csv)], 'device_ids' => $devices])->assertOk()->assertJsonPath('data.inserted', 2)->assertJsonPath('data.duplicates', 2);
        $this->assertDatabaseCount('attendance_imports', 2);
        $this->assertDatabaseCount('attendance_logs', 2);
        $this->assertDatabaseCount('attendance_import_punches', 4);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'evaluation' => 'on_time']);
    }

    public function test_machine_specific_ids_keep_different_people_separate_and_accept_explicit_numeric_codes(): void
    {
        $first = $this->operationalEmployee();
        $second = $this->postJson('/api/v1/employees', ['name' => 'Second employee', 'enroll_id' => '002', 'staff_category' => 'other', 'service_grade' => 'III'])->assertCreated()->json('data.id');
        $devices = [];
        foreach (['Gate 1' => $first, 'Gate 2' => $second] as $name => $employee) {
            $d = $this->postJson('/api/v1/attendance/devices', ['name' => $name, 'format' => 'device_text', 'in_code' => '0', 'out_code' => '1', 'shared_enrollment' => false])->assertCreated()->json('data.id');
            $devices[] = $d;
            $this->postJson('/api/v1/attendance/device-enrollments', ['device_id' => $d, 'enroll_id' => '0007', 'employee_id' => $employee])->assertOk();
        }
        $raw = "0007\t2026-11-02 08:30:00\t1\t0\t0\t0\n0007\t2026-11-02 16:15:00\t1\t1\t0\t0\n";
        $this->postJson('/api/v1/attendance/import', ['files' => [UploadedFile::fake()->createWithContent('one.dat', $raw), UploadedFile::fake()->createWithContent('two.dat', $raw)], 'device_ids' => $devices])->assertOk()->assertJsonPath('data.inserted', 4);
        foreach ([$first, $second] as $employee) {
            $this->assertDatabaseHas('attendance_days', ['employee_id' => $employee, 'evaluation' => 'on_time']);
        }
        $this->postJson('/api/v1/attendance/device-enrollments', ['device_id' => $devices[0], 'enroll_id' => '0007', 'employee_id' => $second])->assertUnprocessable();
        $unknown = UploadedFile::fake()->createWithContent('bad-state.dat', "0007 2026-11-03 08:30:00 1 4 0 0\n");
        $this->postJson('/api/v1/attendance/import', ['file' => $unknown, 'device_id' => $devices[0]])->assertUnprocessable();
        $this->assertDatabaseCount('attendance_imports', 2);
    }

    public function test_viewer_cannot_create_machines_mappings_or_temporary_test_data(): void
    {
        $this->signIn(Role::Viewer);
        $this->postJson('/api/v1/attendance/devices', [])->assertForbidden();
        $this->postJson('/api/v1/attendance/device-enrollments', [])->assertForbidden();
        $this->postJson('/api/v1/attendance/test-runs', [])->assertForbidden();
        $this->getJson('/api/v1/attendance/test-runs')->assertOk()->assertJsonPath('available', false);
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

    public function test_monthly_late_allowances_reset_and_covered_third_late_uses_short_leave(): void
    {
        $id = $this->operationalEmployee();
        $csv = "enroll_id,timestamp,state\n";
        foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-12-01'] as $date) {
            $csv .= "001,$date 08:45:00,IN\n001,$date 16:30:00,OUT\n";
        }
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('covered.csv', $csv)])->assertOk();
        foreach (['02' => 'late_covered', '03' => 'late_covered', '04' => 'short_leave', '05' => 'short_leave', '06' => 'half_day'] as $day => $evaluation) {
            $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'date' => '2026-11-'.$day, 'evaluation' => $evaluation]);
        }
        $this->assertDatabaseHas('attendance_days', ['date' => '2026-12-01', 'evaluation' => 'late_covered', 'late_sequence' => 1]);
        $this->assertDatabaseCount('leave_ledger', 0);
        $this->getJson('/api/v1/attendance/workspace?month=2026-11&employee_id='.$id)->assertOk()->assertJsonCount(30, 'days')->assertJsonPath('employees.0.summary.half_days', 1)->assertJsonPath('employees.0.summary.short_leave', 2);
    }

    public function test_backdated_import_replays_later_monthly_evaluations(): void
    {
        $id = $this->operationalEmployee();
        $csv = "enroll_id,timestamp,state\n001,2026-11-04 08:45:00,IN\n001,2026-11-04 16:30:00,OUT\n001,2026-11-05 08:45:00,IN\n001,2026-11-05 16:30:00,OUT\n";
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('later.csv', $csv)])->assertOk();
        $earlier = "enroll_id,timestamp,state\n001,2026-11-02 08:45:00,IN\n001,2026-11-02 16:30:00,OUT\n";
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('earlier.csv', $earlier)])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $id, 'date' => '2026-11-05', 'late_sequence' => 3, 'evaluation' => 'short_leave']);
    }

    public function test_short_leave_boundaries_early_departure_and_incomplete_pl_records(): void
    {
        $id = $this->operationalEmployee();
        $csv = "enroll_id,timestamp,state\n001,2026-11-02 10:00:00,IN\n001,2026-11-02 16:15:00,OUT\n001,2026-11-03 10:01:00,IN\n001,2026-11-03 16:15:00,OUT\n001,2026-11-04 08:30:00,IN\n001,2026-11-04 14:45:00,OUT\n001,2026-11-05 08:30:00,IN\n001,2026-11-05 14:44:00,OUT\n";
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('windows.csv', $csv)])->assertOk();
        foreach (['02' => 'short_leave', '03' => 'half_day', '04' => 'short_leave', '05' => 'half_day'] as $day => $evaluation) {
            $this->assertDatabaseHas('attendance_days', ['date' => '2026-11-'.$day, 'evaluation' => $evaluation]);
        }
        DB::table('employee_profiles')->where('employee_id', $id)->update(['staff_category' => 'pl']);
        $this->postJson('/api/v1/attendance/recalculate', ['month' => '2026-11'])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['date' => '2026-11-02', 'evaluation' => 'policy_review', 'half_day' => 0]);
    }

    public function test_workspace_filters_division_order_and_scoped_pdf_reports(): void
    {
        $id = $this->operationalEmployee();
        $finance = Division::create(['division' => 'Finance Division']);
        $admin = Division::create(['division' => 'Administration Division']);
        Employee::whereKey($id)->update(['division' => $finance->id]);
        Employee::create(['name' => 'Another employee', 'division' => $admin->id, 'is_active' => 1]);
        $this->getJson('/api/v1/attendance/workspace?month=2026-11')->assertOk()->assertJsonPath('divisions.0.division', 'Administration Division')->assertJsonPath('employees.0.name', 'Another employee');
        $this->getJson('/api/v1/attendance/workspace?month=2026-11&division_id='.$finance->id)->assertOk()->assertJsonCount(1, 'employees')->assertJsonPath('employees.0.id', $id);
        $this->getJson('/api/v1/attendance/workspace?month=2026-11&search=doesnotexist')->assertOk()->assertJsonCount(0, 'employees');
        $this->getJson('/api/v1/attendance/workspace?month=2026-11&date=2026-12-01')->assertUnprocessable();
        foreach (['attendance', 'divisional', 'leave-utilization', 'late-warning'] as $type) {
            $r = $this->get('/api/v1/reports/pdf?type='.$type.'&month=2026-11&division_id='.$finance->id.'&employee_id='.$id);
            $r->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $r->getContent());
        }
    }

    public function test_approved_short_leaves_without_punches_consume_monthly_allowance(): void
    {
        $id = $this->operationalEmployee();
        foreach (['2026-11-02', '2026-11-03'] as $date) {
            $leave = $this->postJson('/api/v1/leaves', ['employee_id' => $id, 'type' => 'short_morning', 'scope' => 'domestic', 'start_date' => $date, 'end_date' => $date, 'minutes' => 30, 'reason' => 'Test'])->assertCreated()->json('data.id');
            $this->postJson('/api/v1/leaves/'.$leave.'/review', ['status' => 'approved'])->assertOk();
        }
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('late.csv', "enroll_id,timestamp,state\n001,2026-11-04 08:45:00,IN\n001,2026-11-04 16:15:00,OUT\n")])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['date' => '2026-11-04', 'evaluation' => 'half_day']);
    }

    public function test_short_leave_approval_without_punches_recalculates_other_dates_in_month(): void
    {
        $id = $this->operationalEmployee();
        $leave = $this->postJson('/api/v1/leaves', ['employee_id' => $id, 'type' => 'short_morning', 'scope' => 'domestic', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'minutes' => 30, 'reason' => 'Test'])->assertCreated()->json('data.id');
        $csv = "enroll_id,timestamp,state\n001,2026-11-03 08:45:00,IN\n001,2026-11-03 16:15:00,OUT\n001,2026-11-04 08:45:00,IN\n001,2026-11-04 16:15:00,OUT\n";
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('before-approval.csv', $csv)])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['date' => '2026-11-04', 'evaluation' => 'short_leave']);
        $this->postJson('/api/v1/leaves/'.$leave.'/review', ['status' => 'approved'])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['date' => '2026-11-04', 'evaluation' => 'half_day']);
        $this->postJson('/api/v1/leaves/'.$leave.'/review', ['status' => 'cancelled'])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['date' => '2026-11-04', 'evaluation' => 'short_leave']);
    }

    public function test_future_approved_short_leaves_are_reserved_against_automatic_conversions(): void
    {
        $id = $this->operationalEmployee();
        foreach (['2026-11-05', '2026-11-06'] as $date) {
            $leave = $this->postJson('/api/v1/leaves', ['employee_id' => $id, 'type' => 'short_morning', 'scope' => 'domestic', 'start_date' => $date, 'end_date' => $date, 'minutes' => 30, 'reason' => 'Test'])->assertCreated()->json('data.id');
            $this->postJson('/api/v1/leaves/'.$leave.'/review', ['status' => 'approved'])->assertOk();
        }
        $this->postJson('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('reserved.csv', "enroll_id,timestamp,state\n001,2026-11-02 08:45:00,IN\n001,2026-11-02 16:15:00,OUT\n")])->assertOk();
        $this->assertDatabaseHas('attendance_days', ['date' => '2026-11-02', 'evaluation' => 'half_day']);
        $this->getJson('/api/v1/attendance/workspace?month=2026-11&employee_id='.$id)->assertOk()->assertJsonPath('employees.0.summary.short_leave', 2);
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
