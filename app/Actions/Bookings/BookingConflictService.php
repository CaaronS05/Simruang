<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BookingConflictService
{
    /**
     * @return Builder<Booking>
     */
    public function overlappingQuery(int $roomId, Carbon $startAt, Carbon $endAt, ?int $exceptBookingId = null): Builder
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::APPROVED])
            ->when($exceptBookingId, fn (Builder $query) => $query->whereKeyNot($exceptBookingId))
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);
    }

    public function hasOverlap(int $roomId, Carbon $startAt, Carbon $endAt, ?int $exceptBookingId = null): bool
    {
        return $this->overlappingQuery($roomId, $startAt, $endAt, $exceptBookingId)->exists();
    }
}
