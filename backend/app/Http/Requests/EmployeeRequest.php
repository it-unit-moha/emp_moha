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
        return ['name' => ['required', 'string', 'max:255'], 'nic' => ['nullable', 'string', 'max:12', Rule::unique('employee_data', 'nic')->ignore($this->route('employee')?->getKey())],
            'emp_no' => ['nullable', 'string', 'max:12'], 'address' => ['nullable', 'string', 'max:255'], 'contact_no' => ['nullable', 'string', 'max:39'],
            'email' => ['nullable', 'email', 'max:35'], 'dob' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:6'], 'division' => ['nullable', 'integer', 'exists:division,id'],
            'unit' => ['nullable', 'string', 'max:4', 'regex:/^[0-9]+$/', 'exists:unit,id'],
            'designation' => ['nullable', 'integer', 'exists:designation,id']];
    }
}
