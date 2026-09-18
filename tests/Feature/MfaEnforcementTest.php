<?php

namespace Tests\Feature;

use App\Filament\Pages\MfaSetup;
use App\Http\Middleware\EnforceMfa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MfaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_expired_grace_period_is_redirected_to_mfa_setup(): void
    {
        $user = User::factory()->create(['mfa_grace_until' => now()->subSecond()]);

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertRedirect(MfaSetup::getUrl(panel: 'admin'));
    }

    public function test_user_with_mfa_enabled_without_session_confirmation_is_logged_out(): void
    {
        $user = User::factory()->create();
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->makeTwoFactorCode());

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertRedirect('/maestro/login');

        $this->assertGuest();
    }

    public function test_user_with_mfa_enabled_and_session_confirmation_can_access_authenticated_public_routes(): void
    {
        $user = User::factory()->create();
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->makeTwoFactorCode());

        $this->actingAs($user)
            ->withSession([EnforceMfa::SESSION_KEY => $user->getKey()])
            ->get('/settings/profile')
            ->assertOk();
    }

    public function test_guardian_only_user_is_exempt_from_mfa(): void
    {
        $user = User::factory()->create(['mfa_grace_until' => now()->subSecond()]);
        $user->assignRole(Role::findByName(User::ROLE_GUARDIAN));
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->makeTwoFactorCode());

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertOk();
    }

    public function test_guardian_with_another_role_is_not_exempt_from_mfa(): void
    {
        $user = User::factory()->create(['mfa_grace_until' => now()->subSecond()]);
        $user->assignRole([
            Role::findByName(User::ROLE_GUARDIAN),
            Role::findByName('Professor'),
        ]);

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertRedirect(MfaSetup::getUrl(panel: 'admin'));
    }
}
