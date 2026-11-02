<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MasterDataService
{
    public function __construct(private ImportedTableWriter $writer) {}

    public function definition(string $module): array
    {
        return match ($module) {
            'divisions' => [Division::class, 'division', 'division'],'units' => [Unit::class, 'unit_name', 'unit'],'designations' => [Designation::class, 'designation', 'designation'],default => abort(404)
        };
    }

    public function delete(Model $record, string $employeeColumn): void
    {
        $this->writer->run(function () use ($record, $employeeColumn) {
            if (Employee::where($employeeColumn, $record->getKey())->exists()) {
                throw ValidationException::withMessages(['record' => 'This record is referenced by employees and cannot be deleted.']);
            }
            $record->delete();
        });
    }
}
