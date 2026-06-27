<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class RejectBookingAction
{
    public function execute(Booking $booking, User $reviewer, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $reviewer, $reason): Booking {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status !== BookingStatus::PENDING) {
                $this->conflict('Hanya booking pending yang dapat ditolak.');
            }

            $lockedBooking->update([
                'status' => BookingStatus::REJECTED,
                'rejection_reason' => $reason,
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
