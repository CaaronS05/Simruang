<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Bookings\ApproveBookingAction;
use App\Actions\Bookings\RejectBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RejectBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled,completed'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'sort_by' => ['nullable', 'in:start_at,end_at,created_at,status'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = Booking::query()
            ->with(['user', 'room', 'facilities', 'reviewer'])
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['room_id'] ?? null, fn ($query, int $roomId) => $query->where('room_id', $roomId))
            ->when($validated['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('start_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('start_at', '<=', $date))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('booking_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('campus_id', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('room', fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($validated['sort_by'] ?? 'created_at', $validated['sort_direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => BookingResource::collection($bookings)->resolve(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
            'links' => [
                'first' => $bookings->url(1),
                'last' => $bookings->url($bookings->lastPage()),
                'prev' => $bookings->previousPageUrl(),
                'next' => $bookings->nextPageUrl(),
            ],
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => new BookingResource($booking->load(['user', 'room', 'facilities', 'reviewer'])),
        ]);
    }

    public function approve(Booking $booking, Request $request, ApproveBookingAction $action): JsonResponse
    {
        $booking = $action->execute($booking, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil disetujui.',
            'data' => new BookingResource($booking),
        ]);
    }

    public function reject(RejectBookingRequest $request, Booking $booking, RejectBookingAction $action): JsonResponse
    {
        $booking = $action->execute($booking, $request->user(), $request->validated('rejection_reason'));

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil ditolak.',
            'data' => new BookingResource($booking),
        ]);
    }
}
