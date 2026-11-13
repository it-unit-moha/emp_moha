<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $table = 'employee_data';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    // Real DB uses inserted_on / updated_on — not Laravel's standard timestamps.
    public $timestamps = false;

    protected $fillable = [
        'name', 'nic', 'address', 'contact_no', 'email', 'dob', 'emp_no',
        'designation', 'unit', 'division', 'gender', 'is_active',
        // Real-DB columns (stored directly on employee_data)
        'date_issued', 'date_expiry', 'image', 'application_form', 'signature',
        'qr_code', 'is_printed',
        // Operational columns added by migration
        'enroll_id', 'staff_category', 'service_grade', 'vacation_days',
    ];

    protected function casts(): array
    {
        return [
            'unit'        => 'string',
            'id'          => 'integer',
            'designation' => 'integer',
            'division'    => 'integer',
            'is_active'   => 'integer',
            'is_printed'  => 'integer',
            'dob'         => 'date:Y-m-d',
            'date_issued' => 'date:Y-m-d',
            'date_expiry' => 'date:Y-m-d',
        ];
    }

    public function divisionRecord(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division', 'id');
    }

    public function unitRecord(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit', 'id');
    }

    public function designationRecord(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation', 'id');
    }

    // No profile relationship — all fields are on employee_data directly.
}
