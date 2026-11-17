<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'unit';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = ['unit_name', 'status', 'division_id'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'status' => 'integer',
            'division_id' => 'integer',
        ];
    }
}
