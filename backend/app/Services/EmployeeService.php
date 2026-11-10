<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeProfile;
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
        return $this->writer->atomic(function () use ($data) {
            $profile = array_intersect_key($data, array_flip(['service_grade', 'enroll_id', 'staff_category', 'vacation_days']));
            $data = array_diff_key($data, $profile);
            $employee = $this->writer->run(function () use ($data) {
                $this->validateAssignments($data);

                return Employee::create([...$data, 'is_active' => 1]);
            });
            EmployeeProfile::updateOrCreate(['employee_id' => $employee->id], $profile);
            app(WorkflowService::class)->initialize($employee->id, (int) now('Asia/Colombo')->year);
            app(WorkflowService::class)->audit('create', 'employee_data', $employee->id);

            return $employee->load('profile');
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return $this->writer->atomic(function () use ($employee, $data) {
            $profile = array_intersect_key($data, array_flip(['service_grade', 'enroll_id', 'staff_category', 'vacation_days']));
            $data = array_diff_key($data, $profile);
            $this->writer->run(function () use ($employee, $data) {
                $this->validateAssignments($data);
                $employee->update($data);

                return $employee->refresh();
            });
            EmployeeProfile::updateOrCreate(['employee_id' => $employee->id], $profile);
            app(WorkflowService::class)->initialize($employee->id, (int) now('Asia/Colombo')->year);
            app(WorkflowService::class)->audit('update', 'employee_data', $employee->id);
            if (array_key_exists('staff_category', $profile)) {
                app(WorkflowService::class)->refreshDates('2000-01-01', '2100-12-31', $employee->id);
            }

            return $employee->refresh()->load('profile');
        });
    }

    public function archive(Employee $employee): void
    {
        $employee->update(['is_active' => 0]);
        app(WorkflowService::class)->audit('archive', 'employee_data', $employee->id);
    }
}
