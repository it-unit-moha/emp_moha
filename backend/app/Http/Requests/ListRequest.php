<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string', 'in:id,name,emp_no'], 'direction' => ['sometimes', 'in:asc,desc'], 'division' => ['nullable', 'integer', 'exists:division,id'], 'unit' => ['nullable', 'integer', 'exists:unit,id'], 'designation' => ['nullable', 'integer', 'exists:designation,id'], 'status' => ['sometimes', 'in:all,active,archived']];
    }
}
