<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SchoolYear extends Model
{
    protected $table = 'schoolyears';

    protected $fillable = [
        'schoolyear',
        'start_date_especializado',
        'end_date_especializado',
        'start_date_profissional',
        'end_date_profissional',
        'start_date_livre',
        'end_date_livre',
        'start_date_registration',
        'end_date_registration',
        'active',
    ];

    public static function createWithExclusiveActive(array $attributes): self
    {
        return DB::transaction(function () use ($attributes): self {
            if ($attributes['active'] ?? false) {
                static::query()->where('active', true)->lockForUpdate()->update(['active' => false]);
            }

            return static::query()->create($attributes);
        });
    }

    public function updateWithExclusiveActive(array $attributes): bool
    {
        return DB::transaction(function () use ($attributes): bool {
            if ($attributes['active'] ?? false) {
                static::query()
                    ->whereKeyNot($this->getKey())
                    ->where('active', true)
                    ->lockForUpdate()
                    ->update(['active' => false]);
            }

            return $this->update($attributes);
        });
    }

    public function courseSubjects()
    {
        return $this->hasMany(CourseSubject::class, 'id_schoolyear');
    }

    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Registration::class,
            'id_schoolyear',
            'id',
            'id',
            'id_student'
        )->distinct();
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'id_schoolyear');
    }
}
