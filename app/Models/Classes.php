<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classes extends Model
{
    protected $fillable = [
        'name',
        'id_course',
        'year',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'id_course');
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class, 'id_class');
    }

    public function classes()
    {
        return $this->belongsToMany(
            Classes::class,
            'schedule_class',      // nome da tabela pivot
            'schedule_id',
            'class_id'
        );
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'schedules_classes', 'id_class', 'id_schedule');
    }

    public function buildings()
    {
        return $this->belongsToMany(Building::class, 'class_buildings', 'id_class', 'id_building')
            ->withTimestamps();
    }
}
