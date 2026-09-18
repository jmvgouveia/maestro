<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\MfaSetup;
use App\Http\Middleware\EnforceMfa;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MfaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_login_rejects_an_invalid_mfa_code_and_accepts_a_valid_one(): void
    {
        $user = $this->createMfaUser();

        $login = Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $login
            ->fillForm(['two_factor_code' => '000000'])
            ->call('authenticate')
            ->assertHasErrors(['data.two_factor_code']);

        $this->assertGuest();

        $login = Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $login
            ->fillForm(['two_factor_code' => $user->makeTwoFactorCode()])
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->getKey(), session(EnforceMfa::SESSION_KEY));
    }

    public function test_guardian_only_user_can_login_without_an_mfa_code(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName(User::ROLE_GUARDIAN));
        $user->createTwoFactorAuth();
        $user->enableTwoFactorAuth();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session(EnforceMfa::SESSION_KEY));
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $user = $this->createMfaUser();
        $recoveryCode = $user->getRecoveryCodes()->first()['code'];

        $login = Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
                'two_factor_code' => $recoveryCode,
            ])
            ->call('authenticate');

        $login
            ->fillForm(['two_factor_code' => $recoveryCode])
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);

        auth()->logout();
        session()->forget(EnforceMfa::SESSION_KEY);

        $login = Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $login
            ->fillForm(['two_factor_code' => $recoveryCode])
            ->call('authenticate')
            ->assertHasErrors(['data.two_factor_code']);

        $this->assertGuest();
    }

    public function test_enabling_mfa_marks_the_current_session_as_confirmed(): void
    {
        $user = User::factory()->create(['mfa_grace_until' => now()->subMinute()]);
        $user->assignRole(Role::findByName('Professor'));

        $this->actingAs($user);

        $component = Livewire::test(MfaSetup::class);
        $code = $user->fresh()->makeTwoFactorCode();

        $component
            ->set('code', $code)
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertSame($user->getKey(), session(EnforceMfa::SESSION_KEY));
    }

    public function test_filament_password_reset_request_page_is_available(): void
    {
        $this->get('/maestro/password-reset/request')->assertOk();
    }

    public function test_password_reset_event_revokes_existing_sessions(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);

        DB::table('sessions')->insert([
            'id' => 'compromised-session',
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        event(new PasswordReset($user));

        $this->assertDatabaseMissing('sessions', ['id' => 'compromised-session']);
        $this->assertNotSame('old-remember-token', $user->fresh()->getRememberToken());
    }

    public function test_new_users_receive_the_configured_mfa_grace_period(): void
    {
        $beforeCreation = now()->addDays((int) config('two-factor.grace_days'))->subSecond();

        $user = User::factory()->create();

        $this->assertNotNull($user->mfa_grace_until);
        $this->assertTrue($user->mfa_grace_until->greaterThan($beforeCreation));
    }

    private function createMfaUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Professor'));
        $user->createTwoFactorAuth();
        $user->enableTwoFactorAuth();

        return $user->fresh();
    }
}
