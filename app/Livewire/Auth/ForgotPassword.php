<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class ForgotPassword extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        if (User::query()->where('email', $this->email)->where('is_active', true)->exists()) {
            Password::sendResetLink($this->only('email'));
        }

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}
