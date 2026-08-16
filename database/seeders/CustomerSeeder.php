<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds store-front customer accounts with a default delivery address each,
 * so the checkout flow can be exercised without registering by hand.
 *
 * Idempotent: keyed by email (unique in the customers table). Addresses are
 * matched on [customer_id, street, number].
 */
class CustomerSeeder extends Seeder
{
    /** Shared password for every seeded customer. */
    private const PASSWORD = '12345';

    public function run(): void
    {
        $customers = [
            [
                'name'  => 'Maria Silva',
                'email' => 'maria@cliente.test',
                'phone' => '(51) 98888-1111',
                'address' => ['Casa', 'Rua dos Andradas', '450', 'Apto 302', 'Centro', 5.00],
            ],
            [
                'name'  => 'João Pereira',
                'email' => 'joao@cliente.test',
                'phone' => '(51) 98888-2222',
                'address' => ['Trabalho', 'Avenida Getúlio Vargas', '1180', null, 'Menino Deus', 8.00],
            ],
            [
                'name'  => 'Luiza Costa',
                'email' => 'luiza@cliente.test',
                'phone' => '(51) 98888-3333',
                'address' => ['Casa', 'Rua Dona Firmina', '77', 'Casa 2', 'Sarandi', 10.00],
            ],
        ];

        foreach ($customers as $data) {
            $customer = Customer::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'phone'    => $data['phone'],
                    'password' => Hash::make(self::PASSWORD),
                ]
            );

            [$label, $street, $number, $complement, $neighborhood] = $data['address'];

            CustomerAddress::firstOrCreate(
                ['customer_id' => $customer->id, 'street' => $street, 'number' => $number],
                [
                    'label'        => $label,
                    'complement'   => $complement,
                    'neighborhood' => $neighborhood,
                    'city'         => 'Porto Alegre',
                    'state'        => 'RS',
                    'zip'          => '90020-000',
                    'is_default'   => true,
                ]
            );
        }
    }
}
