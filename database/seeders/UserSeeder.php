<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@ecosight.test'],
            [
                'name' => 'EcoSight Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::factory()->count(5)->create();
    }
}
