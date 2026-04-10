<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_registration_is_disabled(): void
    {
        // Registration is intentionally disabled — users are created by admin.
        $this->get('/register')->assertStatus(404);
    }
}
