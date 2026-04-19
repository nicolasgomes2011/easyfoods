<?php

namespace Tests\Unit;

use App\Enums\ProductAvailabilityStatus;
use PHPUnit\Framework\TestCase;

class ProductAvailabilityStatusTest extends TestCase
{
    public function test_available_label(): void
    {
        $this->assertSame('Disponível', ProductAvailabilityStatus::Available->label());
    }

    public function test_unavailable_label(): void
    {
        $this->assertSame('Indisponível', ProductAvailabilityStatus::Unavailable->label());
    }

    public function test_out_of_stock_label(): void
    {
        $this->assertSame('Sem estoque', ProductAvailabilityStatus::OutOfStock->label());
    }

    public function test_only_available_is_orderable(): void
    {
        $this->assertTrue(ProductAvailabilityStatus::Available->isOrderable());
    }

    public function test_unavailable_is_not_orderable(): void
    {
        $this->assertFalse(ProductAvailabilityStatus::Unavailable->isOrderable());
    }

    public function test_out_of_stock_is_not_orderable(): void
    {
        $this->assertFalse(ProductAvailabilityStatus::OutOfStock->isOrderable());
    }

    public function test_all_cases_have_labels(): void
    {
        foreach (ProductAvailabilityStatus::cases() as $status) {
            $this->assertNotEmpty($status->label(), "{$status->value} deve ter um label");
        }
    }

    public function test_values_are_correct(): void
    {
        $this->assertSame('available',   ProductAvailabilityStatus::Available->value);
        $this->assertSame('unavailable', ProductAvailabilityStatus::Unavailable->value);
        $this->assertSame('out_of_stock', ProductAvailabilityStatus::OutOfStock->value);
    }
}
