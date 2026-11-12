<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $t) {
            $t->string('evaluation', 30)->nullable();
            $t->text('remarks')->nullable();
            $t->unsignedInteger('early_minutes')->default(0);
            $t->unsignedInteger('late_sequence')->default(0);
            $t->unsignedInteger('short_sequence')->default(0);
            $t->decimal('half_day', 3, 1)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', fn (Blueprint $t) => $t->dropColumn(['evaluation', 'remarks', 'early_minutes', 'late_sequence', 'short_sequence', 'half_day']));
    }
};
