<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Validator;

class EmployeeService
{
    public function __construct(private ImportedTableWriter $writer) {}

    private function validateAssignments(array $data): void
    {
        Validator::make($data, [
            'division' => ['nullable', 'exists:division,id'],
            'unit' => ['nullable', 'exists:unit,id'],
            'designation' => ['nullable', 'exists:designation,id'],
        ])->validate();
    }

    public function create(array $data): Employee
    {
        return $this->writer->run(function () use ($data) {
            $this->validateAssignments($data);

            return Employee::create([...$data, 'is_active' => 1]);
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return $this->writer->run(function () use ($employee, $data) {
            $this->validateAssignments($data);
            $employee->update($data);

            return $employee->refresh();
        });
    }

    public function archive(Employee $employee): void
    {
        $employee->update(['is_active' => 0]);
    }
}
