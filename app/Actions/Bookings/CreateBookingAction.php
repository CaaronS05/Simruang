<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBookingAction
{
    public function __construct(private readonly BookingConflictService $conflicts) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): Booking
    {
        $startAt = Carbon::parse($data['start_at'], 'Asia/Jakarta');
        $endAt = Carbon::parse($data['end_at'], 'Asia/Jakarta');
        $facilities = $data['facilities'] ?? [];

        $this->validateBusinessRules((int) $data['room_id'], $startAt, $endAt, $facilities);

        return DB::transaction(function () use ($user, $data, $startAt, $endAt, $facilities): Booking {
            $booking = Booking::query()->create([
                'booking_code' => $this->generateBookingCode($startAt),
                'user_id' => $user->id,
                'room_id' => $data['room_id'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => BookingStatus::PENDING,
            ]);

            $syncData = [];
            foreach ($facilities as $facility) {
                $syncData[$facility['facility_id']] = ['quantity' => $facility['quantity']];
            }

            if ($syncData !== []) {
                $booking->facilities()->sync($syncData);
            }

            return $booking->load(['room', 'facilities']);
        });
    }

    /**
     * @param  array<int, array<string, int>>  $facilities
     */
    private function validateBusinessRules(int $roomId, Carbon $startAt, Carbon $endAt, array $facilities): void
    {
        $room = Room::query()->findOrFail($roomId);

        if ($room->status !== RoomStatus::AVAILABLE) {
            $this->conflict('Ruangan tidak tersedia untuk peminjaman.');
        }

        if ($startAt->toDateString() <= now('Asia/Jakarta')->toDateString()) {
            $this->conflict('Peminjaman minimal diajukan H-1.');
        }

        if ($startAt->isWeekend()) {
            $this->conflict('Tanggal mulai peminjaman tidak boleh Sabtu atau Minggu.');
        }

        if ($startAt->diffInHours($endAt, false) > 24) {
            $this->conflict('Durasi peminjaman maksimal 24 jam.');
        }

        if ($this->conflicts->hasOverlap($roomId, $startAt, $endAt)) {
            $this->conflict('Jadwal ruangan bertabrakan dengan peminjaman lain.');
        }

        foreach ($facilities as $requestedFacility) {
            $facility = Facility::query()->findOrFail($requestedFacility['facility_id']);

            if (! $facility->is_active) {
                $this->conflict('Fasilitas tidak aktif tidak dapat dipinjam.');
            }

            if ($requestedFacility['quantity'] > $facility->global_stock) {
                $this->conflict('Stok fasilitas global tidak mencukupi.');
            }
        }
    }

    private function generateBookingCode(Carbon $startAt): string
    {
        do {
            $code = 'SIM-'.$startAt->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Booking::query()->where('booking_code', $code)->exists());

        return $code;
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
