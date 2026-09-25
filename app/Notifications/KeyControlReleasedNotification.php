<?php

namespace App\Notifications;

use App\Models\KeyControl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class KeyControlReleasedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly KeyControl $keyControl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $room = $this->keyControl->room?->name ?? 'sala desconhecida';

        return (new MailMessage)
            ->subject('Chave não devolvida - '.$room)
            ->greeting('Olá, '.$this->keyControl->holderDisplayName())
            ->line("A chave da sala {$room} não foi registada como devolvida.")
            ->line('A sala foi libertada para permitir a continuidade das atividades, mas a chave continua associada a si até ser entregue.')
            ->line('Por favor, entregue a chave ao porteiro assim que possível.')
            ->withSymfonyMessage(function (Email $message): void {
                $message->getHeaders()->addTextHeader('X-Email-Audit-Notification-Type', static::class);
            });
    }
}
