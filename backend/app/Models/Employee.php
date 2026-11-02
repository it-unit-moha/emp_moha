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

    // Imported MySQL owns inserted_on / updated_on. Do not synthesize Laravel timestamps.
    public $timestamps = false;

    protected $fillable = ['name', 'nic', 'address', 'contact_no', 'email', 'dob', 'emp_no', 'designation', 'unit', 'division', 'gender', 'is_active'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'designation' => 'integer', 'division' => 'integer', 'is_active' => 'integer', 'dob' => 'date:Y-m-d'];
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
}
