<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ActivateAccount;
use App\Models\User;
use App\Notifications\UserActivationNotification;
use App\Services\UserActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_email_uses_a_public_png_logo_without_attachments(): void
    {
        $user = User::factory()->create();
        $mail = (new UserActivationNotification($user, 'token'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertStringContainsString('images/maestro-logo-light.png', $html);
        $this->assertStringContainsString('width="220" height="154"', $html);
        $this->assertStringNotContainsString('images/maestro-logo-light.svg', $html);
    }

    public function test_activation_sets_password_and_preserves_multiple_roles(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'old-password',
        ]);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Aluno', 'guard_name' => 'web']);
        $user->assignRole(['Super Admin', 'Aluno']);

        $user->forceFill(['is_active' => false])->save();
        $token = app(UserActivationService::class)->issue($user);

        Livewire::test(ActivateAccount::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'New-password-123!')
            ->set('password_confirmation', 'New-password-123!')
            ->call('activate')
            ->assertRedirect('/maestro');

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('New-password-123!', $user->password));
        $this->assertTrue($user->hasAllRoles(['Super Admin', 'Aluno']));
        $this->assertNull($user->activation_token);
    }

    public function test_reissuing_activation_token_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $service = app(UserActivationService::class);
        $oldToken = $service->issue($user);
        $newToken = $service->issue($user);

        $this->assertNotSame($oldToken, $newToken);
        $this->expectException(ValidationException::class);
        $service->activate($oldToken, $user->email, 'New-password-123!');
    }
}
