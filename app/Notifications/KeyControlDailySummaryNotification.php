<?php

namespace App\Notifications;

use App\Models\KeyControl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class KeyControlDailySummaryNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $records) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = collect($this->records)->map(function (KeyControl $record): string {
            return sprintf('%s - %s (chave não devolvida)', $record->room?->name ?? 'Sala desconhecida', $record->holderDisplayName());
        });

        $message = (new MailMessage)
            ->subject('Resumo diário do controlo de chaves')
            ->greeting('Resumo diário')
            ->line('Foram registadas as seguintes ocorrências de chaves não devolvidas:');

        foreach ($lines as $line) {
            $message->line('• '.$line);
        }

        return $message->withSymfonyMessage(function (Email $message): void {
            $message->getHeaders()->addTextHeader('X-Email-Audit-Notification-Type', static::class);
        });
    }
}
