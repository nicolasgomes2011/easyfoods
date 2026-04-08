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
        $users = [
            [
                'name'     => 'Admin',
                'email'    => 'admin@easyfoods.demo',
                'password' => Hash::make('password'),
                'role'     => UserRole::Admin,
            ],
            [
                'name'     => 'Gerente Demo',
                'email'    => 'manager@easyfoods.demo',
                'password' => Hash::make('password'),
                'role'     => UserRole::Manager,
            ],
            [
                'name'     => 'Atendente Demo',
                'email'    => 'attendant@easyfoods.demo',
                'password' => Hash::make('password'),
                'role'     => UserRole::Attendant,
            ],
            [
                'name'     => 'Cozinha Demo',
                'email'    => 'kitchen@easyfoods.demo',
                'password' => Hash::make('password'),
                'role'     => UserRole::Kitchen,
            ],
            [
                'name'     => 'Entregador Demo',
                'email'    => 'delivery@easyfoods.demo',
                'password' => Hash::make('password'),
                'role'     => UserRole::Delivery,
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(['email' => $data['email']], $data);
        }
    }
}
