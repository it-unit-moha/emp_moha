<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canWrite() ?? false;
    }

    public function rules(): array
    {
<<<<<<< Updated upstream
        return ['name' => ['required', 'string', 'max:255']];
=======
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'division_id' => ['nullable', 'integer', 'exists:division,id'],
        ];
>>>>>>> Stashed changes
    }
}
