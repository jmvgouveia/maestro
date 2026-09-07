<?php

namespace App\Notifications;

use Filament\Notifications\Auth\ResetPassword as FilamentResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

class ResetPasswordNotification extends FilamentResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reposição da palavra-passe do Maestro')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para repor a palavra-passe da sua conta no Maestro.')
            ->action('Repor palavra-passe', $this->url)
            ->line('Este link é válido durante '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
            ->line('Se não efetuou este pedido, não precisa de realizar nenhuma ação.')
            ->withSymfonyMessage(function (Email $message): void {
                $message->getHeaders()->addTextHeader('X-Email-Audit-Notification-Type', static::class);
            });
    }
}
