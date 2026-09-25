<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyControlReportRecipient extends Model
{
    protected $fillable = ['type', 'name', 'user_id', 'email', 'is_active', 'report_types'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'report_types' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailAddress(): ?string
    {
        return $this->user?->email ?: $this->email;
    }

    public function displayName(): ?string
    {
        return $this->name ?: $this->user?->name;
    }
}
