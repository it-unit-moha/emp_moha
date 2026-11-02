<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'nic' => $this->nic, 'emp_no' => $this->emp_no, 'email' => $this->email, 'address' => $this->address, 'contact_no' => $this->contact_no, 'dob' => $this->dob?->format('Y-m-d'), 'gender' => $this->gender, 'is_active' => $this->is_active,
            'division' => $this->division, 'unit' => $this->unit, 'designation' => $this->designation,
            'division_name' => $this->whenLoaded('divisionRecord', fn () => $this->divisionRecord?->division),
            'unit_name' => $this->whenLoaded('unitRecord', fn () => $this->unitRecord?->unit_name),
            'designation_name' => $this->whenLoaded('designationRecord', fn () => $this->designationRecord?->designation),
        ];
    }
}
