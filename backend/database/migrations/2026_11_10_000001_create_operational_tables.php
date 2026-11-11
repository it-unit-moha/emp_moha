<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive tables: never change the imported employee register.
        Schema::create('employee_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('employee_id')->unique();
            $t->string('service_grade', 60)->nullable();
            $t->string('enroll_id', 40)->nullable()->unique();
            $t->string('staff_category', 10)->nullable();
            $t->unsignedInteger('vacation_days')->nullable();
            $t->timestamps();
        });
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name');
            $t->string('kind', 20);
            $t->timestamps();
        });
        Schema::create('attendance_imports', function (Blueprint $t) {
            $t->id();
            $t->string('filename');
            $t->string('sha256', 64)->unique();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedInteger('inserted')->default(0);
            $t->unsignedInteger('duplicates')->default(0);
            $t->timestamps();
        });
        Schema::create('attendance_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('import_id');
            $t->unsignedInteger('employee_id')->index();
            $t->dateTime('punched_at');
            $t->string('state', 3);
            $t->unique(['employee_id', 'punched_at', 'state'], 'unique_employee_punch');
        });
        Schema::create('attendance_days', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('employee_id');
            $t->date('date');
            $t->dateTime('actual_in')->nullable();
            $t->dateTime('actual_out')->nullable();
            $t->dateTime('required_out')->nullable();
            $t->unsignedInteger('late_minutes')->default(0);
            $t->string('status', 30);
            $t->timestamps();
            $t->unique(['employee_id', 'date']);
        });
        Schema::create('leave_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('employee_id');
            $t->unsignedInteger('year');
            $t->string('type', 20);
            $t->decimal('opening', 8, 2)->default(0);
            $t->unique(['employee_id', 'year', 'type']);
        });
        Schema::create('leave_applications', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('employee_id')->index();
            $t->string('type', 25);
            $t->date('start_date');
            $t->date('end_date');
            $t->string('scope', 10)->default('domestic');
            $t->unsignedInteger('minutes')->nullable();
            $t->decimal('days', 8, 2);
            $t->string('reason', 1000);
            $t->string('status', 15)->default('pending');
            $t->unsignedBigInteger('requested_by');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamps();
        });
        Schema::create('leave_ledger', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('account_id')->index();
            $t->decimal('amount', 8, 2);
            $t->string('reference')->unique();
            $t->string('description');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('report_runs', function (Blueprint $t) {
            $t->id();
            $t->string('month', 7);
            $t->string('type');
            $t->string('path');
            $t->timestamps();
            $t->unique(['month', 'type']);
        });
    }

    public function down(): void
    {
        foreach (['report_runs', 'audit_logs', 'leave_ledger', 'leave_applications', 'leave_accounts', 'attendance_days', 'attendance_logs', 'attendance_imports', 'holidays', 'employee_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
