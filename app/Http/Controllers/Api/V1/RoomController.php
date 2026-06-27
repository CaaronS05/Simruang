<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'building' => ['nullable', 'string'],
            'minimum_capacity' => ['nullable', 'integer', 'min:1'],
            'maximum_capacity' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'in:name,code,capacity,building,created_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rooms = Room::query()
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('building', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['building'] ?? null, fn ($query, string $building) => $query->where('building', $building))
            ->when($validated['minimum_capacity'] ?? null, fn ($query, int $capacity) => $query->where('capacity', '>=', $capacity))
            ->when($validated['maximum_capacity'] ?? null, fn ($query, int $capacity) => $query->where('capacity', '<=', $capacity))
            ->orderBy($validated['sort_by'] ?? 'created_at', $validated['sort_direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => RoomResource::collection($rooms)->resolve(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'last_page' => $rooms->lastPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
            ],
            'links' => [
                'first' => $rooms->url(1),
                'last' => $rooms->url($rooms->lastPage()),
                'prev' => $rooms->previousPageUrl(),
                'next' => $rooms->nextPageUrl(),
            ],
        ]);
    }

    public function show(Room $room): JsonResponse
    {
        $room->load('facilities');

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => new RoomResource($room),
        ]);
    }
}
