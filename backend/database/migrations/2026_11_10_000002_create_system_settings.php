<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
