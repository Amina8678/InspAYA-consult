<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCaseNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_and_username_are_lowercased_on_save(): void
    {
        $user = User::factory()->create([
            'email' => 'User.Name@Example.Com',
            'username' => 'MixedCase_User',
        ]);

        $this->assertSame('user.name@example.com', $user->email);
        $this->assertSame('mixedcase_user', $user->username);

        $fresh = $user->fresh();
        $this->assertSame('user.name@example.com', $fresh->email);
        $this->assertSame('mixedcase_user', $fresh->username);
    }

    public function test_user_lookup_matches_exact_lowercase_stored_value(): void
    {
        User::factory()->create([
            'email' => 'SearchMe@Example.Test',
        ]);

        $found = User::firstWhere('email', 'searchme@example.test');
        $this->assertNotNull($found);
    }
}
