<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Room\SyncRoomFacilitiesRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

class RoomFacilityController extends Controller
{
    public function sync(SyncRoomFacilitiesRequest $request, Room $room): JsonResponse
    {
        $syncData = [];

        foreach ($request->validated('facilities') as $facility) {
            $syncData[$facility['facility_id']] = ['quantity' => $facility['quantity']];
        }

        $room->facilities()->sync($syncData);
        $room->load('facilities');

        return response()->json([
            'success' => true,
            'message' => 'Fasilitas ruangan berhasil disinkronkan.',
            'data' => new RoomResource($room),
        ]);
    }
}
