<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class UserActivationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $user, private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ative a sua conta no Maestro')
            ->greeting("Olá, {$this->user->name}")
            ->line('Foi criada uma conta para si no Maestro.')
            ->action('Ativar conta', route('activation', ['token' => $this->token]))
            ->line('Este convite é válido durante 7 dias e só pode ser utilizado uma vez.')
            ->withSymfonyMessage(function (Email $message): void {
                $message->getHeaders()->addTextHeader('X-Email-Audit-Notification-Type', static::class);
            });
    }
}
