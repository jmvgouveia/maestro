<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KeyControl extends Model
{
    /** @use HasFactory<\Database\Factories\KeyControlFactory> */
    use HasFactory;

    public const RELEASE_TYPE_MANUAL = 'manual';

    public const RELEASE_TYPE_DAILY_CLOSURE = 'daily_closure';

    protected $fillable = [
        'room_id',
        'holder_type',
        'holder_id',
        'picked_up_at',
        'returned_at',
        'room_released_at',
        'room_released_by',
        'release_type',
        'release_reason',
        'individual_notification_sent_at',
        'included_in_summary_at',
        'pick_up_observations',
        'return_observations',
        'picked_up_by',
        'returned_by',
        'corrected_by',
        'correction_reason',
        'original_key_control_id',
        'is_corrected',
    ];

    protected function casts(): array
    {
        return [
            'picked_up_at' => 'datetime',
            'returned_at' => 'datetime',
            'room_released_at' => 'datetime',
            'individual_notification_sent_at' => 'datetime',
            'included_in_summary_at' => 'date',
            'is_corrected' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function roomReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'room_released_by');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function originalKeyControl(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_key_control_id');
    }

    public function correctedVersions()
    {
        return $this->hasMany(self::class, 'original_key_control_id');
    }

    public function floorKeyAccesses()
    {
        return $this->hasMany(KeyControlFloorKeyAccess::class, 'key_control_id')->orderByDesc('accessed_at');
    }

    public function events()
    {
        return $this->hasMany(KeyControlEvent::class, 'key_control_id')->orderByDesc('occurred_at');
    }

    public function originalEventKeyControlId(): int
    {
        return $this->original_key_control_id ?? $this->getKey();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('returned_at')->whereNull('room_released_at')->where('is_corrected', false);
    }

    public function scopeWithoutCorrections($query)
    {
        return $query->where('is_corrected', false);
    }

    public function scopePendingKeys($query)
    {
        return $query->whereNull('returned_at')->where('is_corrected', false);
    }

    public function scopeRoomReleased($query)
    {
        return $query->whereNotNull('room_released_at')->whereNull('returned_at')->where('is_corrected', false);
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    public function isActive(): bool
    {
        return $this->returned_at === null && $this->room_released_at === null && ! $this->is_corrected;
    }

    public function isRoomReleased(): bool
    {
        return $this->room_released_at !== null && $this->returned_at === null && ! $this->is_corrected;
    }

    public function isCorrected(): bool
    {
        return $this->is_corrected;
    }

    public function isPendingKey(): bool
    {
        return $this->returned_at === null && ! $this->is_corrected;
    }

    public function statusLabel(): string
    {
        if ($this->is_corrected) {
            return 'Corrigida';
        }

        if ($this->returned_at !== null) {
            return 'Devolvida';
        }

        if ($this->release_type === self::RELEASE_TYPE_DAILY_CLOSURE) {
            return 'Fecho diário automático';
        }

        if ($this->room_released_at !== null) {
            return 'Sala libertada pelo porteiro';
        }

        if ($this->relationLoaded('floorKeyAccesses')
            ? $this->floorKeyAccesses->isNotEmpty()
            : $this->floorKeyAccesses()->exists()) {
            return 'Abertura com chave do funcionário de piso';
        }

        return 'Por devolver';
    }

    public function releaseTypeLabel(): ?string
    {
        return match ($this->release_type) {
            self::RELEASE_TYPE_MANUAL => 'Libertação manual',
            self::RELEASE_TYPE_DAILY_CLOSURE => 'Fecho diário automático',
            default => null,
        };
    }

    public function holderDisplayName(): string
    {
        $holder = $this->holder;

        if ($holder instanceof Teacher) {
            return $holder->name ?? ('Professor #'.$holder->getKey());
        }

        if ($holder instanceof Student) {
            return $holder->name ?? ('Aluno #'.$holder->getKey());
        }

        return 'Desconhecido';
    }

    public function holderDisplayLabel(): string
    {
        $holder = $this->holder;

        if ($holder === null) {
            return 'Desconhecido';
        }

        return sprintf('%s - %s', $holder->number ?: 'Sem número', $holder->name);
    }

    public function holderTypeLabel(): string
    {
        return match ($this->holder_type) {
            Teacher::class => 'Professor',
            Student::class => 'Aluno',
            default => 'Desconhecido',
        };
    }
}
