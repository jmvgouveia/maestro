<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserActivationNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserActivationService
{
    public const TOKEN_TTL_HOURS = 168;

    public function issue(User $user): string
    {
        $token = Str::random(40);

        $user->forceFill([
            'is_active' => false,
            'activation_token' => Hash::make($token),
            'activation_token_expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
        ])->save();

        return $token;
    }

    public function issueAndNotify(User $user): string
    {
        $token = $this->issue($user);

        if ($this->hasDeliverableEmail($user)) {
            $user->notify(new UserActivationNotification($user, $token));
        }

        return $token;
    }

    public function hasDeliverableEmail(User $user): bool
    {
        return filled($user->email) && ! str_ends_with(strtolower($user->email), '@ceam.com');
    }

    public function activate(string $token, string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (
            ! $user
            || $user->is_active
            || ! $user->activation_token
            || $user->activation_token_expires_at?->isPast()
            || ! Hash::check($token, $user->activation_token)
        ) {
            throw ValidationException::withMessages([
                'token' => 'O convite é inválido ou expirou. Solicite um novo convite à administração.',
            ]);
        }

        $user->forceFill([
            'password' => $password,
            'is_active' => true,
            'activated_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'activation_token' => null,
            'activation_token_expires_at' => null,
        ])->save();

        return $user;
    }
}
