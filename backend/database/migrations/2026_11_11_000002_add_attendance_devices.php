<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->string('format', 30)->default('delimited');
            $t->string('in_code', 20)->default('IN');
            $t->string('out_code', 20)->default('OUT');
            $t->boolean('shared_enrollment')->default(true);
            $t->timestamps();
        });
        Schema::create('attendance_device_enrollments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('device_id');
            $t->string('enroll_id', 40);
            $t->unsignedInteger('employee_id');
            $t->unique(['device_id', 'enroll_id']);
        });
        Schema::table('attendance_imports', function (Blueprint $t) {
            $t->dropUnique(['sha256']);
            $t->index('sha256');
            $t->unsignedBigInteger('device_id')->nullable();
            $t->string('source_key', 64)->nullable()->unique();
        });
        foreach (DB::table('attendance_imports')->get() as $import) {
            DB::table('attendance_imports')->where('id', $import->id)->update(['source_key' => hash('sha256', '0|'.$import->sha256)]);
        }
        Schema::create('attendance_import_punches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('import_id')->index();
            $t->unsignedInteger('employee_id')->index();
            $t->string('enroll_id', 40);
            $t->dateTime('punched_at');
            $t->string('state', 3);
            $t->unique(['import_id', 'employee_id', 'punched_at', 'state'], 'unique_import_punch_source');
        });
        Schema::create('attendance_test_runs', function (Blueprint $t) {
            $t->id();
            $t->string('token', 20)->unique();
            $t->json('manifest');
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // Forward-only: device provenance and import history must not be discarded by rollback.
    }
};
