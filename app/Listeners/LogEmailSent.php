<?php

namespace App\Listeners;

use App\Models\EmailAudit;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

class LogEmailSent
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        if (method_exists($message, 'getOriginalMessage')) {
            $message = $message->getOriginalMessage();
        }

        if (! $message instanceof Email) {
            return;
        }

        $correlationId = $this->resolveCorrelationId($message);

        if ($correlationId === null) {
            return;
        }

        EmailAudit::query()
            ->where('correlation_id', $correlationId)
            ->update([
                'status' => EmailAudit::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);
    }

    private function resolveCorrelationId(Email $message): ?string
    {
        $header = $message->getHeaders()->get('X-Email-Audit-Correlation-Id');

        if ($header !== null) {
            return $header->getBody();
        }

        return LogEmailSending::$pending[spl_object_id($message)] ?? null;
    }
}
