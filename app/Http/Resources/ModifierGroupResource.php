<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'required'   => $this->required,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'sort_order' => $this->sort_order,
            'modifiers'  => ModifierResource::collection($this->whenLoaded('modifiers')),
        ];
    }
}
