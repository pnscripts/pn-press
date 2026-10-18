<?php

namespace Tests\Feature\Press;

use App\Models\User;
use App\Press\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        Permissions::sync();
        $user = User::factory()->create();
        $user->assignRole('editor');

        return $user;
    }

    public function test_admin_users_can_set_up_an_authenticator_app_on_their_profile(): void
    {
        $this->actingAs($this->editor())
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Authenticator app');
    }

    public function test_the_secret_and_recovery_codes_are_stored_encrypted_and_hidden(): void
    {
        $user = $this->editor();
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $user->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

        $raw = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $raw->app_authentication_secret);
        $this->assertStringNotContainsString('code-one', (string) $raw->app_authentication_recovery_codes);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
        $this->assertArrayNotHasKey('app_authentication_secret', $user->fresh()->toArray());
    }
}
