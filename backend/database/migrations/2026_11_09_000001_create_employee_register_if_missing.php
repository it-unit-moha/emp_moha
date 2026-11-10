<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fresh = ! Schema::hasTable('employee_data') && ! Schema::hasTable('division') && ! Schema::hasTable('unit') && ! Schema::hasTable('designation');
        foreach (['division' => 'division', 'unit' => 'unit_name', 'designation' => 'designation'] as $name => $column) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, function (Blueprint $t) use ($name, $column) {
                    $t->engine = 'InnoDB';
                    $t->increments('id');
                    $t->string($column)->nullable();
                    if ($name === 'division') {
                        $t->integer('status')->default(1);
                    }
                });
            }
        }
        if (! Schema::hasTable('employee_data')) {
            Schema::create('employee_data', function (Blueprint $t) use ($fresh) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->string('name')->nullable();
                $t->string('nic', 12)->nullable()->unique();
                $t->string('emp_no', 12)->nullable();
                $t->string('email', 35)->nullable();
                $t->string('address')->nullable();
                $t->string('contact_no', 39)->nullable();
                $t->date('dob')->nullable();
                $t->string('gender', 6)->nullable();
                foreach (['division', 'designation'] as $field) {
                    $t->unsignedInteger($field)->nullable()->index();
                }
                if ($fresh) {
                    $t->unsignedInteger('unit')->nullable()->index();
                } else {
                    $t->string('unit', 4)->nullable();
                }
                $t->integer('is_active')->nullable();
                $t->timestamp('inserted_on')->useCurrent();
                $t->dateTime('updated_on')->nullable();
                if ($fresh) {
                    $t->foreign('division')->references('id')->on('division')->restrictOnDelete();
                    $t->foreign('unit')->references('id')->on('unit')->restrictOnDelete();
                    $t->foreign('designation')->references('id')->on('designation')->restrictOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // Forward-only preservation: a rollback must never delete an employee register.
    }
};
