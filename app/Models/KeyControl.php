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

    protected $fillable = [
        'room_id',
        'holder_type',
        'holder_id',
        'picked_up_at',
        'returned_at',
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

    public function scopeActive($query)
    {
        return $query->whereNull('returned_at')->where('is_corrected', false);
    }

    public function scopeWithoutCorrections($query)
    {
        return $query->where('is_corrected', false);
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    public function isActive(): bool
    {
        return $this->returned_at === null && ! $this->is_corrected;
    }

    public function isCorrected(): bool
    {
        return $this->is_corrected;
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
