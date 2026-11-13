<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Core identity
            'id'               => $this->id,
            'name'             => $this->name,
            'nic'              => $this->nic,
            'emp_no'           => $this->emp_no,
            'email'            => $this->email,
            'address'          => $this->address,
            'contact_no'       => $this->contact_no,
            'dob'              => $this->dob?->format('Y-m-d'),
            'gender'           => $this->gender,
            'is_active'        => $this->is_active,

            // Assignment
            'division'         => $this->division,
            'unit'             => $this->unit,
            'designation'      => $this->designation,
            'division_name'    => $this->whenLoaded('divisionRecord', fn () => $this->divisionRecord?->division),
            'unit_name'        => $this->whenLoaded('unitRecord', fn () => $this->unitRecord?->unit_name),
            'designation_name' => $this->whenLoaded('designationRecord', fn () => $this->designationRecord?->designation),

            // Documents — stored as file paths on employee_data
            'date_of_issue'          => $this->date_issued?->format('Y-m-d'),
            'date_of_expiry'         => $this->date_expiry?->format('Y-m-d'),
            'has_photo'              => (bool) $this->image,
            'has_application_form'   => (bool) $this->application_form,
            'has_signature'          => (bool) $this->signature,
            'qr_code'                => $this->qr_code,

            // Attendance / operational (added by migration)
            'enroll_id'       => $this->enroll_id,
            'staff_category'  => $this->staff_category,
            'service_grade'   => $this->service_grade,
            'vacation_days'   => $this->vacation_days,
        ];
    }
}
