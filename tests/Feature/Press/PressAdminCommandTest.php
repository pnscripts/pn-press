<?php

namespace Tests\Feature\Press;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PressAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_who_can_open_the_panel(): void
    {
        $this->artisan('press:admin', ['email' => 'owner@example.test'])
            ->expectsQuestion('Password (at least 10 characters)', 'a-long-password')
            ->expectsQuestion('Repeat the password', 'a-long-password')
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue(Hash::check('a-long-password', $user->password));
    }

    public function test_short_or_mismatched_passwords_create_nothing(): void
    {
        $this->artisan('press:admin', ['email' => 'x@example.test'])
            ->expectsQuestion('Password (at least 10 characters)', 'short')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_an_existing_user_is_promoted(): void
    {
        $user = User::factory()->create(['email' => 'ed@example.test']);

        $this->artisan('press:admin', ['email' => 'ed@example.test', '--role' => 'editor'])->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole('editor'));
    }
}
