<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\Restaurant;

/**
 * Guest checkout has no customer login: the phone typed at checkout is what
 * identifies a returning customer. Finds (or creates) the restaurant's customer
 * for that phone and keeps the latest name. No phone digits, no customer.
 */
class ResolveCheckoutCustomer
{
    public function handle(Restaurant $restaurant, ?string $name, ?string $phone): ?Customer
    {
        $digits = self::normalizePhone($phone);

        if ($digits === null) {
            return null;
        }

        $customer = Customer::firstOrNew([
            'restaurant_id' => $restaurant->id,
            'phone'         => $digits,
        ]);

        $customer->name = trim((string) $name) ?: 'Cliente';
        $customer->save();

        return $customer;
    }

    /**
     * Digits only, without the Brazil country code, so "(11) 98765-4321",
     * "11987654321" and "+55 11 98765-4321" are the same customer.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $digits = substr($digits, 2);
        }

        return $digits === '' ? null : $digits;
    }
}
