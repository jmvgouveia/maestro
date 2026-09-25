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
        'show_on_occupancy_map',
    ];

    protected function casts(): array
    {
        return ['show_on_occupancy_map' => 'boolean'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'id_building');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'id_room');
    }

    public function features()
    {
        return $this->belongsToMany(RoomFeature::class, 'room_feature');
    }

    public function activeKeyControl()
    {
        return $this->hasOne(KeyControl::class, 'room_id')
            ->whereNull('returned_at')
            ->whereNull('room_released_at')
            ->where('is_corrected', false)
            ->latest('picked_up_at');
    }

    public function pendingKeyControls()
    {
        return $this->hasMany(KeyControl::class, 'room_id')
            ->whereNull('returned_at')
            ->where('is_corrected', false)
            ->orderByDesc('picked_up_at');
    }

    public function activeFloorKeyAccess()
    {
        return $this->hasOne(KeyControlFloorKeyAccess::class, 'room_id')
            ->whereNull('ended_at')
            ->latest('accessed_at');
    }

    public function isAvailableFor(string $description, string $weekday): bool
    {
        return !$this->schedules()
            ->where('description', $description)
            ->where('weekday', $weekday)
            ->exists();
    }
}
