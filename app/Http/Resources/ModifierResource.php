<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'modifier_group_id' => $this->modifier_group_id,
            'name'              => $this->name,
            'price'             => (float) $this->price,
            'is_available'      => $this->is_available,
            'sort_order'        => $this->sort_order,
        ];
    }
}
