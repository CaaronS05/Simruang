<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = config('simruang.admin');
        $requiredKeys = ['name', 'campus_id', 'email', 'password'];
        $missingKeys = array_filter($requiredKeys, fn (string $key): bool => blank($admin[$key] ?? null));

        if ($missingKeys !== []) {
            throw new RuntimeException('Admin seeder requires these environment values: '.implode(', ', array_map(fn (string $key): string => 'ADMIN_'.strtoupper($key), $missingKeys)));
        }

        User::query()->updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'campus_id' => $admin['campus_id'],
                'password' => Hash::make($admin['password']),
                'role' => UserRole::ADMIN,
                'is_active' => true,
            ],
        );
    }
}
