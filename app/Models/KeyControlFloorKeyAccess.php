<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyControlFloorKeyAccess extends Model
{
    protected $fillable = [
        'key_control_id',
        'room_id',
        'accessed_at',
        'accessed_by',
        'occupant_type',
        'occupant_id',
        'ended_at',
        'ended_by',
        'reason',
    ];

    protected function casts(): array
    {
        return ['accessed_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function keyControl(): BelongsTo
    {
        return $this->belongsTo(KeyControl::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function accessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accessed_by');
    }

    public function occupant(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'occupant_id');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function events()
    {
        return $this->hasMany(KeyControlEvent::class, 'floor_key_access_id')->orderByDesc('occurred_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
