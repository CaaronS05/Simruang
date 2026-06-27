<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\CancelBookingAction;
use App\Actions\Bookings\CreateBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'sort_by' => ['nullable', 'in:start_at,end_at,created_at,status'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = Booking::query()
            ->with(['room', 'facilities', 'reviewer'])
            ->where('user_id', $request->user()->id)
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['room_id'] ?? null, fn ($query, int $roomId) => $query->where('room_id', $roomId))
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('start_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('start_at', '<=', $date))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('booking_code', 'like', "%{$search}%")
                        ->orWhereHas('room', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($validated['sort_by'] ?? 'created_at', $validated['sort_direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated($bookings);
    }

    public function store(StoreBookingRequest $request, CreateBookingAction $action): JsonResponse
    {
        $booking = $action->execute($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibuat.',
            'data' => new BookingResource($booking),
        ], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_if($booking->user_id !== $request->user()->id, 404);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => new BookingResource($booking->load(['room', 'facilities', 'reviewer'])),
        ]);
    }

    public function cancel(Request $request, Booking $booking, CancelBookingAction $action): JsonResponse
    {
        abort_if($booking->user_id !== $request->user()->id, 404);

        $booking = $action->execute($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibatalkan.',
            'data' => new BookingResource($booking),
        ]);
    }

    private function paginated($paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => BookingResource::collection($paginator)->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
