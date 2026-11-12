<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date_of_issue' => $this->profile?->date_of_issue,
            'date_of_expiry' => $this->profile?->date_of_expiry,
            'has_application_form' => (bool) $this->profile?->application_form_path,
            'has_signature' => (bool) $this->profile?->signature_path,
            'has_photo' => (bool) $this->profile?->photo_path,
            'service_grade' => $this->profile?->service_grade, 'enroll_id' => $this->profile?->enroll_id, 'staff_category' => $this->profile?->staff_category, 'vacation_days' => $this->profile?->vacation_days,
            'id' => $this->id, 'name' => $this->name, 'nic' => $this->nic, 'emp_no' => $this->emp_no, 'email' => $this->email, 'address' => $this->address, 'contact_no' => $this->contact_no, 'dob' => $this->dob?->format('Y-m-d'), 'gender' => $this->gender, 'is_active' => $this->is_active,
            'division' => $this->division, 'unit' => $this->unit, 'designation' => $this->designation,
            'division_name' => $this->whenLoaded('divisionRecord', fn () => $this->divisionRecord?->division),
            'unit_name' => $this->whenLoaded('unitRecord', fn () => $this->unitRecord?->unit_name),
            'designation_name' => $this->whenLoaded('designationRecord', fn () => $this->designationRecord?->designation),
        ];
    }
}
