<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class AttendanceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canWrite() ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', File::types(['csv', 'txt', 'dat'])->max(config('galms.attendance.max_upload_kb'))->extensions(config('galms.attendance.accepted_extensions'))]];
    }
}
