<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/** Short table locks preserve assignment checks on the imported MyISAM tables. */
class ImportedTableWriter
{
    public function usesLegacyLocks(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }
        $engines = DB::select("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('employee_data','division','unit','designation')");

        return collect($engines)->contains(fn ($row) => strtoupper($row->ENGINE) !== 'INNODB');
    }

    public function atomic(Closure $operation): mixed
    {
        // MyISAM LOCK TABLES commits implicitly; do not pretend it can roll back.
        return $this->usesLegacyLocks() ? $operation() : DB::transaction($operation);
    }

    public function run(Closure $operation): mixed
    {
        if (! $this->usesLegacyLocks()) {
            return DB::transaction($operation);
        }
        DB::statement('LOCK TABLES employee_data WRITE, division WRITE, unit WRITE, designation WRITE');
        try {
            return $operation();
        } finally {
            DB::statement('UNLOCK TABLES');
        }
    }
}
