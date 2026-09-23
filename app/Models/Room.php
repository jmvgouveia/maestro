<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    protected $fillable = [
        'name',
        'description',
        'id_building',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'id_building');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'id_room');
    }

    public function activeKeyControl()
    {
        return $this->hasOne(KeyControl::class, 'room_id')
            ->whereNull('returned_at')
            ->where('is_corrected', false)
            ->latest('picked_up_at');
    }

    public function isAvailableFor(string $description, string $weekday): bool
    {
        return !$this->schedules()
            ->where('description', $description)
            ->where('weekday', $weekday)
            ->exists();
    }
}
