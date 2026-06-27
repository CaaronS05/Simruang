<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Room */
class RoomResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'building' => $this->building,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'status' => $this->status->value,
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
