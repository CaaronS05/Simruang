<?php

namespace Database\Seeders;

use App\Enums\RoomStatus;
use App\Models\Facility;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            [
                'code' => 'AULA-A',
                'name' => 'Aula Utama Gedung A',
                'building' => 'Gedung A',
                'floor' => '1',
                'capacity' => 250,
                'description' => 'Aula utama untuk kegiatan berskala besar.',
                'facilities' => [
                    'Proyektor' => 2,
                    'Sound System' => 1,
                    'Mikrofon' => 4,
                    'Kabel HDMI' => 2,
                ],
            ],
            [
                'code' => 'RRE-01',
                'name' => 'Ruang Rapat Eksekutif',
                'building' => 'Gedung A',
                'floor' => '2',
                'capacity' => 24,
                'description' => 'Ruang rapat untuk koordinasi dan pertemuan eksekutif.',
                'facilities' => [
                    'Proyektor' => 1,
                    'Sound System' => 1,
                    'Mikrofon' => 2,
                    'Kabel HDMI' => 1,
                ],
            ],
            [
                'code' => 'LAB-KOM-1',
                'name' => 'Laboratorium Komputer 1',
                'building' => 'Gedung B',
                'floor' => '3',
                'capacity' => 40,
                'description' => 'Laboratorium komputer untuk praktikum dan pelatihan.',
                'facilities' => [
                    'Proyektor' => 1,
                    'Kabel HDMI' => 1,
                ],
            ],
            [
                'code' => 'SEM-A',
                'name' => 'Ruang Seminar A',
                'building' => 'Gedung C',
                'floor' => '1',
                'capacity' => 80,
                'description' => 'Ruang seminar untuk kuliah umum dan workshop.',
                'facilities' => [
                    'Proyektor' => 1,
                    'Sound System' => 1,
                    'Mikrofon' => 2,
                    'Kabel HDMI' => 1,
                ],
            ],
        ];

        foreach ($rooms as $roomData) {
            $facilityQuantities = $roomData['facilities'];
            unset($roomData['facilities']);

            $room = Room::query()->updateOrCreate(
                ['code' => $roomData['code']],
                [
                    ...$roomData,
                    'photo_path' => null,
                    'status' => RoomStatus::AVAILABLE,
                ],
            );

            $facilityIds = Facility::query()
                ->whereIn('name', array_keys($facilityQuantities))
                ->pluck('id', 'name');

            $syncData = [];
            foreach ($facilityQuantities as $facilityName => $quantity) {
                if ($facilityIds->has($facilityName)) {
                    $syncData[$facilityIds[$facilityName]] = ['quantity' => $quantity];
                }
            }

            $room->facilities()->syncWithoutDetaching($syncData);
        }
    }
}
