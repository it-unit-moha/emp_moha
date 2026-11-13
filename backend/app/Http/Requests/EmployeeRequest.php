<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canWrite() ?? false;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->getKey();

        return [
            // Personal details
            'name'        => ['required', 'string', 'max:255'],
            'nic'         => ['nullable', 'string', 'max:12',
                              Rule::unique('employee_data', 'nic')->ignore($employeeId)],
            'emp_no'      => ['nullable', 'string', 'max:12'],
            'address'     => ['nullable', 'string', 'max:255'],
            'contact_no'  => ['nullable', 'string', 'max:39'],
            'email'       => ['nullable', 'email', 'max:35'],
            'dob'         => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender'      => ['nullable', 'string', 'max:6'],

            // Assignment
            'division'    => ['nullable', 'integer', 'exists:division,id'],
            'unit'        => ['nullable', 'string', 'max:4', 'regex:/^[0-9]+$/', 'exists:unit,id'],
            'designation' => ['nullable', 'integer', 'exists:designation,id'],
            'is_active'   => ['sometimes', 'required', 'integer', Rule::in([0, 1])],

            // Documents — real DB stores file paths on employee_data
            'date_of_issue'  => ['nullable', 'date_format:Y-m-d'],
            'date_of_expiry' => ['nullable', 'date_format:Y-m-d',
                                 ...($this->filled('date_of_issue') ? ['after_or_equal:date_of_issue'] : [])],
            'application_form' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'signature'        => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'photo'            => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],

            // Attendance operational fields
            'enroll_id'     => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                                Rule::unique('employee_data', 'enroll_id')->ignore($employeeId)],
            'staff_category' => ['nullable', Rule::in(['pl', 'other'])],
            'service_grade'  => ['nullable', 'string', 'max:60'],
            'vacation_days'  => ['nullable', 'integer', Rule::in([24, 28])],
        ];
    }
}
