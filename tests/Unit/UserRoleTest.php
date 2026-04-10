<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    // --- canAccessAdmin ---

    public function test_all_roles_can_access_admin(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertTrue($role->canAccessAdmin(), "{$role->value} should access admin");
        }
    }

    // --- canManageCatalog ---

    public function test_admin_can_manage_catalog(): void
    {
        $this->assertTrue(UserRole::Admin->canManageCatalog());
    }

    public function test_manager_can_manage_catalog(): void
    {
        $this->assertTrue(UserRole::Manager->canManageCatalog());
    }

    public function test_attendant_cannot_manage_catalog(): void
    {
        $this->assertFalse(UserRole::Attendant->canManageCatalog());
    }

    public function test_kitchen_cannot_manage_catalog(): void
    {
        $this->assertFalse(UserRole::Kitchen->canManageCatalog());
    }

    public function test_delivery_cannot_manage_catalog(): void
    {
        $this->assertFalse(UserRole::Delivery->canManageCatalog());
    }

    // --- canManageOrders ---

    public function test_admin_can_manage_orders(): void
    {
        $this->assertTrue(UserRole::Admin->canManageOrders());
    }

    public function test_manager_can_manage_orders(): void
    {
        $this->assertTrue(UserRole::Manager->canManageOrders());
    }

    public function test_attendant_can_manage_orders(): void
    {
        $this->assertTrue(UserRole::Attendant->canManageOrders());
    }

    public function test_kitchen_cannot_manage_orders(): void
    {
        $this->assertFalse(UserRole::Kitchen->canManageOrders());
    }

    public function test_delivery_cannot_manage_orders(): void
    {
        $this->assertFalse(UserRole::Delivery->canManageOrders());
    }

    // --- canManageUsers ---

    public function test_only_admin_can_manage_users(): void
    {
        $this->assertTrue(UserRole::Admin->canManageUsers());

        $nonAdmin = [UserRole::Manager, UserRole::Attendant, UserRole::Kitchen, UserRole::Delivery];

        foreach ($nonAdmin as $role) {
            $this->assertFalse($role->canManageUsers(), "{$role->value} should not manage users");
        }
    }

    // --- canManageSettings ---

    public function test_admin_can_manage_settings(): void
    {
        $this->assertTrue(UserRole::Admin->canManageSettings());
    }

    public function test_manager_can_manage_settings(): void
    {
        $this->assertTrue(UserRole::Manager->canManageSettings());
    }

    public function test_attendant_cannot_manage_settings(): void
    {
        $this->assertFalse(UserRole::Attendant->canManageSettings());
    }

    public function test_kitchen_cannot_manage_settings(): void
    {
        $this->assertFalse(UserRole::Kitchen->canManageSettings());
    }

    public function test_delivery_cannot_manage_settings(): void
    {
        $this->assertFalse(UserRole::Delivery->canManageSettings());
    }
}
