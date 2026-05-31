<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'gomes.nicolas.2011@gmail.com'],
            [
                'name'     => 'Nicolas Gomes',
                'password' => Hash::make('12345'),
                'role'     => UserRole::Admin,
            ]
        );
    }
}
