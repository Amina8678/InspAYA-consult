<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C6: guests hitting protected admin routes must be redirected to the admin
 * login page, not receive a 500 because no `login` route exists.
 */
class GuestRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_visiting_admin_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_guest_visiting_a_nested_admin_route_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/media')->assertRedirect(route('admin.login'));
    }

    public function test_json_requests_get_401_instead_of_a_redirect(): void
    {
        $this->getJson('/admin')->assertUnauthorized();
    }
}
