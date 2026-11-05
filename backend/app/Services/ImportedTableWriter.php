<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/** Short table locks preserve assignment checks on the imported MyISAM tables. */
class ImportedTableWriter
{
    public function run(Closure $operation): mixed
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return $operation();
        }
        DB::statement('LOCK TABLES employee_data WRITE, division WRITE, unit WRITE, designation WRITE');
        try {
            return $operation();
        } finally {
            DB::statement('UNLOCK TABLES');
        }
    }
}
