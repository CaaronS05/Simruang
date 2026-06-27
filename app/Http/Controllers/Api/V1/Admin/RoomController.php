<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function store(StoreRoomRequest $request): JsonResponse
    {
        $data = $request->safe()->except('photo');
        $data['photo_path'] = $request->file('photo')?->store('rooms', 'public');

        $room = Room::query()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil dibuat.',
            'data' => new RoomResource($room),
        ], 201);
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $data = $request->safe()->except('photo');
        $oldPhotoPath = $room->photo_path;
        $newPhotoPath = $request->file('photo')?->store('rooms', 'public');

        if ($newPhotoPath) {
            $data['photo_path'] = $newPhotoPath;
        }

        DB::transaction(function () use ($room, $data): void {
            $room->update($data);
        });

        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil diperbarui.',
            'data' => new RoomResource($room->refresh()),
        ]);
    }

    public function destroy(Room $room): JsonResponse
    {
        if ($room->bookings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan sudah memiliki histori booking. Ubah status menjadi inactive.',
                'errors' => (object) [],
            ], 409);
        }

        $photoPath = $room->photo_path;

        DB::transaction(function () use ($room): void {
            $room->delete();
        });

        if ($photoPath) {
            Storage::disk('public')->delete($photoPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil dihapus.',
            'data' => (object) [],
        ]);
    }
}
