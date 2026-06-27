<?php

namespace App\Http\Resources;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Facility */
class FacilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'global_stock' => $this->global_stock,
            'condition' => $this->condition,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'is_active' => $this->is_active,
            'quantity' => $this->whenPivotLoaded('facility_room', fn (): int => $this->pivot->quantity),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
