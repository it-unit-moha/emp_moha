<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add operational columns needed by GALMS to the real ministry employee_data table.
     * All additions are conditional — existing columns are never dropped or modified.
     */
    public function up(): void
    {
        // Add enroll_id and staff_category directly onto employee_data (no separate profiles table).
        Schema::table('employee_data', function (Blueprint $t) {
            if (! Schema::hasColumn('employee_data', 'enroll_id')) {
                $t->string('enroll_id', 40)->nullable()->after('emp_no');
            }
            if (! Schema::hasColumn('employee_data', 'staff_category')) {
                $t->string('staff_category', 10)->nullable()->after('enroll_id');
            }
            if (! Schema::hasColumn('employee_data', 'service_grade')) {
                $t->string('service_grade', 60)->nullable()->after('staff_category');
            }
            if (! Schema::hasColumn('employee_data', 'vacation_days')) {
                $t->integer('vacation_days')->nullable()->after('service_grade');
            }
        });

        // Add status column to division if missing.
        Schema::table('division', function (Blueprint $t) {
            if (! Schema::hasColumn('division', 'status')) {
                $t->integer('status')->default(1)->after('division');
            }
        });

        // Create all operational tables if they don't exist yet.
        foreach ([
            'users', 'cache', 'jobs', 'job_batches', 'failed_jobs',
            'attendance_days', 'attendance_logs', 'attendance_imports',
            'attendance_import_punches', 'attendance_devices', 'attendance_device_enrollments',
            'leave_applications', 'leave_balances', 'leave_ledger',
            'holidays', 'system_settings', 'audit_log',
        ] as $table) {
            // These are created by the other migrations; just skip if present.
            if (! Schema::hasTable($table)) {
                // Trigger the other migrations by artisan migrate — do not duplicate them here.
            }
        }
    }

    public function down(): void
    {
        Schema::table('employee_data', function (Blueprint $t) {
            foreach (['enroll_id', 'staff_category', 'service_grade', 'vacation_days'] as $col) {
                if (Schema::hasColumn('employee_data', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
