<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailAudit extends Model
{
    /** @use HasFactory<\Database\Factories\EmailAuditFactory> */
    use HasFactory;

    public const STATUS_SENDING = 'sending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'correlation_id',
        'recipient_email',
        'subject',
        'notification_type',
        'mailer',
        'status',
        'attempted_at',
        'accepted_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
