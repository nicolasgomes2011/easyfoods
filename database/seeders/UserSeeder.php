<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the admin account plus one staff account per role, so every panel
 * (catálogo, pedidos, cozinha, entregador) can be exercised right after a reset.
 *
 * Idempotent: keyed by email. An existing user is never overwritten, so a
 * password changed by hand survives re-seeding.
 */
class UserSeeder extends Seeder
{
    /** Shared password for every seeded account. */
    private const PASSWORD = '12345';

    public function run(): void
    {
        $users = [
            ['gomes.nicolas.2011@gmail.com', 'Nicolas Gomes',  UserRole::Admin],
            ['gerente@easyfoods.test',       'Ana Gerente',    UserRole::Manager],
            ['atendente@easyfoods.test',     'Bruno Balcão',   UserRole::Attendant],
            ['cozinha@easyfoods.test',       'Carla Cozinha',  UserRole::Kitchen],
            ['entregador@easyfoods.test',    'Diego Entrega',  UserRole::Delivery],
        ];

        foreach ($users as [$email, $name, $role]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name'      => $name,
                    'password'  => Hash::make(self::PASSWORD),
                    'role'      => $role,
                    'is_active' => true,
                ]
            );
        }
    }
}
