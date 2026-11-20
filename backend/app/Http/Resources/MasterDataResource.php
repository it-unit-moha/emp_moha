<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->resource->getAttribute(match ($this->resource->getTable()) {
                'division' => 'division',
                'unit' => 'unit_name',
                'designation' => 'designation',
            }),
            'status' => $this->resource->getAttribute('status'),
        ];

        if ($this->resource->getTable() === 'unit') {
            $data['division_id'] = $this->resource->getAttribute('division_id');
        }

        return $data;
    }
}
