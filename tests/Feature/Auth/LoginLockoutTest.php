<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());

        // Even the correct password is rejected while locked.
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(422);
    }

    public function test_successful_login_resets_failed_attempt_counter(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }
}
