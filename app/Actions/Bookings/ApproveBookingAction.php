<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ApproveBookingAction
{
    public function __construct(private readonly BookingConflictService $conflicts) {}

    public function execute(Booking $booking, User $reviewer): Booking
    {
        return DB::transaction(function () use ($booking, $reviewer): Booking {
            $lockedBooking = Booking::query()
                ->with(['room', 'facilities'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($lockedBooking->status === BookingStatus::APPROVED) {
                return $lockedBooking->load(['user', 'room', 'facilities', 'reviewer']);
            }

            if ($lockedBooking->status !== BookingStatus::PENDING) {
                $this->conflict('Booking sudah bukan pending.');
            }

            if ($lockedBooking->room->status !== RoomStatus::AVAILABLE) {
                $this->conflict('Ruangan tidak tersedia untuk approval.');
            }

            if ($lockedBooking->start_at->lte(now('Asia/Jakarta'))) {
                $this->conflict('Waktu booking sudah lewat.');
            }

            if ($this->conflicts->hasOverlap($lockedBooking->room_id, $lockedBooking->start_at, $lockedBooking->end_at, $lockedBooking->id)) {
                $this->conflict('Jadwal ruangan bertabrakan dengan peminjaman lain.');
            }

            foreach ($lockedBooking->facilities as $requestedFacility) {
                $facility = Facility::query()->lockForUpdate()->findOrFail($requestedFacility->id);
                $quantity = $requestedFacility->pivot->quantity;

                if ($quantity > $facility->global_stock) {
                    $this->conflict('Stok fasilitas global tidak mencukupi.');
                }

                $facility->decrement('global_stock', $quantity);
            }

            $lockedBooking->update([
                'status' => BookingStatus::APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('Asia/Jakarta'),
            ]);

            return $lockedBooking->load(['user', 'room', 'facilities', 'reviewer']);
        });
    }

    private function conflict(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) [],
        ], 409));
    }
}
