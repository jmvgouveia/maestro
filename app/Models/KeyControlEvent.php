<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class KeyControlEvent extends Model
{
    public const ROOM_RELEASED = 'room_released';

    public const KEY_PICKED_UP = 'key_picked_up';

    public const FLOOR_KEY_OPENED = 'floor_key_opened';

    public const FLOOR_USE_ENDED = 'floor_use_ended';

    public const KEY_RETURNED = 'key_returned';

    public const CORRECTED = 'corrected';

    protected $fillable = [
        'key_control_id',
        'floor_key_access_id',
        'event_type',
        'occurred_at',
        'performed_by',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function keyControl(): BelongsTo
    {
        return $this->belongsTo(KeyControl::class, 'key_control_id');
    }

    public function floorKeyAccess(): BelongsTo
    {
        return $this->belongsTo(KeyControlFloorKeyAccess::class, 'floor_key_access_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public static function log(
        int $keyControlId,
        string $eventType,
        ?int $performedBy = null,
        ?array $data = null,
        ?int $floorKeyAccessId = null,
        ?Carbon $occurredAt = null
    ): self {
        return self::create([
            'key_control_id' => $keyControlId,
            'floor_key_access_id' => $floorKeyAccessId,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt ?? now(),
            'performed_by' => $performedBy,
            'data' => $data,
        ]);
    }

    public static function snapshotKeyControl(KeyControl $keyControl): array
    {
        return [
            'room_id' => $keyControl->room_id,
            'holder_type' => $keyControl->holder_type,
            'holder_id' => $keyControl->holder_id,
            'picked_up_at' => $keyControl->picked_up_at?->toDateTimeString(),
            'returned_at' => $keyControl->returned_at?->toDateTimeString(),
            'room_released_at' => $keyControl->room_released_at?->toDateTimeString(),
            'release_type' => $keyControl->release_type,
            'release_reason' => $keyControl->release_reason,
            'pick_up_observations' => $keyControl->pick_up_observations,
            'return_observations' => $keyControl->return_observations,
            'picked_up_by' => $keyControl->picked_up_by,
            'returned_by' => $keyControl->returned_by,
            'room_released_by' => $keyControl->room_released_by,
        ];
    }

    public static function snapshotFloorKeyAccess(KeyControlFloorKeyAccess $access): array
    {
        return [
            'key_control_id' => $access->key_control_id,
            'room_id' => $access->room_id,
            'accessed_at' => $access->accessed_at?->toDateTimeString(),
            'accessed_by' => $access->accessed_by,
            'occupant_type' => $access->occupant_type,
            'occupant_id' => $access->occupant_id,
            'ended_at' => $access->ended_at?->toDateTimeString(),
            'ended_by' => $access->ended_by,
            'reason' => $access->reason,
        ];
    }
}
