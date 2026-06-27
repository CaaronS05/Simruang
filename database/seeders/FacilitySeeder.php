<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $facilities = [
            [
                'name' => 'Proyektor',
                'description' => 'Proyektor portabel untuk presentasi.',
                'global_stock' => 8,
            ],
            [
                'name' => 'Sound System',
                'description' => 'Perangkat audio untuk kegiatan ruangan.',
                'global_stock' => 4,
            ],
            [
                'name' => 'Kursi Ekstra',
                'description' => 'Kursi tambahan untuk kebutuhan acara.',
                'global_stock' => 120,
            ],
            [
                'name' => 'Mikrofon',
                'description' => 'Mikrofon kabel dan nirkabel.',
                'global_stock' => 12,
            ],
            [
                'name' => 'Laptop',
                'description' => 'Laptop pendukung kegiatan presentasi.',
                'global_stock' => 6,
            ],
            [
                'name' => 'Kabel HDMI',
                'description' => 'Kabel HDMI untuk koneksi perangkat display.',
                'global_stock' => 20,
            ],
        ];

        foreach ($facilities as $facility) {
            Facility::query()->updateOrCreate(
                ['name' => $facility['name']],
                [
                    ...$facility,
                    'condition' => 'good',
                    'photo_path' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
