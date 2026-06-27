<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CancelBookingAction
{
    public function execute(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status !== BookingStatus::PENDING) {
                $this->conflict('Hanya booking pending yang dapat dibatalkan.');
            }

            $lockedBooking->update([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now('Asia/Jakarta'),
            ]);

            return $lockedBooking->load(['room', 'facilities', 'reviewer']);
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
