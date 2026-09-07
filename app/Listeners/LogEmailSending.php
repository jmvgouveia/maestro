<?php

namespace App\Listeners;

use App\Models\EmailAudit;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class LogEmailSending
{
    /**
     * Correlações pendentes indexadas pelo identificador da mensagem Symfony.
     * Fallback caso o header customizado não seja preservado por algum mailer.
     */
    public static array $pending = [];

    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        if (! $message instanceof Email) {
            return;
        }

        $correlationId = (string) Str::uuid();

        $message->getHeaders()->addTextHeader('X-Email-Audit-Correlation-Id', $correlationId);
        static::$pending[spl_object_id($message)] = $correlationId;

        $recipients = $message->getTo();
        $recipient = $recipients !== [] ? $recipients[0]->getAddress() : null;

        $notificationHeader = $message->getHeaders()->get('X-Email-Audit-Notification-Type');

        EmailAudit::create([
            'correlation_id' => $correlationId,
            'recipient_email' => $recipient ?? '',
            'subject' => $message->getSubject() ?? '',
            'notification_type' => $notificationHeader?->getBody(),
            'mailer' => config('mail.default', 'unknown'),
            'status' => EmailAudit::STATUS_SENDING,
            'attempted_at' => now(),
        ]);
    }
}
