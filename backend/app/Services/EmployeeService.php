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
            'division'    => ['nullable', 'exists:division,id'],
            'unit'        => ['nullable', 'exists:unit,id'],
            'designation' => ['nullable', 'exists:designation,id'],
        ])->validate();
    }

    public function create(array $data): Employee
    {
        return $this->writer->atomic(function () use ($data) {
            // Map frontend field names to real DB column names
            $data = $this->mapColumns($data);

            $employee = $this->writer->run(function () use ($data) {
                $this->validateAssignments($data);
                return Employee::create(['is_active' => 1, ...$data]);
            });

            app(WorkflowService::class)->initialize($employee->id, (int) now('Asia/Colombo')->year);
            app(WorkflowService::class)->audit('create', 'employee_data', $employee->id);

            return $employee->load(['divisionRecord', 'unitRecord', 'designationRecord']);
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return $this->writer->atomic(function () use ($employee, $data) {
            $data = $this->mapColumns($data);
            $categoryChanged = array_key_exists('staff_category', $data)
                && $data['staff_category'] !== $employee->staff_category;

            $this->writer->run(function () use ($employee, $data) {
                $this->validateAssignments($data);
                $employee->update($data);
                return $employee->refresh();
            });

            app(WorkflowService::class)->initialize($employee->id, (int) now('Asia/Colombo')->year);
            app(WorkflowService::class)->audit('update', 'employee_data', $employee->id);

            if ($categoryChanged) {
                app(WorkflowService::class)->refreshDates('2000-01-01', '2100-12-31', $employee->id);
            }

            return $employee->refresh()->load(['divisionRecord', 'unitRecord', 'designationRecord']);
        });
    }

    public function archive(Employee $employee): void
    {
        $employee->update(['is_active' => 0]);
        app(WorkflowService::class)->audit('archive', 'employee_data', $employee->id);
    }

    /**
     * Map frontend field names → real DB column names, and strip non-column keys.
     */
    private function mapColumns(array $data): array
    {
        // Frontend sends date_of_issue/date_of_expiry; real DB uses date_issued/date_expiry
        if (array_key_exists('date_of_issue', $data)) {
            $data['date_issued'] = $data['date_of_issue'];
            unset($data['date_of_issue']);
        }
        if (array_key_exists('date_of_expiry', $data)) {
            $data['date_expiry'] = $data['date_of_expiry'];
            unset($data['date_of_expiry']);
        }
        // File path fields are handled by EmployeeController before calling this service
        // (photo_path, application_form_path, signature_path are already remapped to image, application_form, signature)

        return $data;
    }
}
