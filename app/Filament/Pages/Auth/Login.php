<?php

namespace App\Filament\Pages\Auth;

use App\Http\Middleware\EnforceMfa;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class Login extends \Filament\Pages\Auth\Login
{
    public bool $mfaChallenge = false;

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if ($this->mfaChallenge) {
            return $this->authenticateMfaChallenge($data['two_factor_code'] ?? null);
        }

        $credentials = $this->getCredentialsFromFormData($data);

        if (! Filament::auth()->validate($credentials)) {
            $this->throwFailureValidationException();
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user instanceof User || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            $this->throwFailureValidationException();
        }

        if (! $user->isMfaExemptGuardian() && $user->hasTwoFactorEnabled()) {
            session()->regenerate();
            session()->put([
                'mfa.pending_user_id' => $user->getKey(),
                'mfa.pending_remember' => (bool) ($data['remember'] ?? false),
                'mfa.pending_expires_at' => now()->addMinutes(5),
            ]);
            $this->mfaChallenge = true;

            return null;
        }

        Filament::auth()->login($user, $data['remember'] ?? false);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function authenticateMfaChallenge(?string $code): ?LoginResponse
    {
        $pendingUserId = session('mfa.pending_user_id');
        $expiresAt = session('mfa.pending_expires_at');
        $user = $pendingUserId ? User::find($pendingUserId) : null;

        if (! $user instanceof User
            || ! $expiresAt instanceof Carbon
            || $expiresAt->isPast()
            || $user->isMfaExemptGuardian()
            || ! $user->hasTwoFactorEnabled()
            || ! $user->canAccessPanel(Filament::getCurrentPanel())
            || ! $user->validateTwoFactorCode($code)) {
            throw ValidationException::withMessages([
                'data.two_factor_code' => 'O código de autenticação é inválido ou expirou.',
            ]);
        }

        $remember = (bool) session('mfa.pending_remember');
        session()->forget(['mfa.pending_user_id', 'mfa.pending_remember', 'mfa.pending_expires_at']);

        Filament::auth()->login($user, $remember);
        session()->put(EnforceMfa::SESSION_KEY, $user->getKey());
        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function getTwoFactorCodeFormComponent(): Component
    {
        return TextInput::make('two_factor_code')
            ->label('Código de autenticação')
            ->helperText('Introduza o código da aplicação autenticadora.')
            ->required(fn (): bool => $this->mfaChallenge)
            ->visible(fn (): bool => $this->mfaChallenge)
            ->autocomplete('one-time-code')
            ->extraInputAttributes(['tabindex' => 3]);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent()->visible(fn (): bool => ! $this->mfaChallenge),
                        $this->getPasswordFormComponent()->visible(fn (): bool => ! $this->mfaChallenge),
                        $this->getTwoFactorCodeFormComponent(),
                        $this->getRememberFormComponent()->visible(fn (): bool => ! $this->mfaChallenge),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'As credenciais ou o código de autenticação estão incorretos.',
        ]);
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label(fn (): string => $this->mfaChallenge ? 'Confirmar código' : 'Entrar');
    }
}
