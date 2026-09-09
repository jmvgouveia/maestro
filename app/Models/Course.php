<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    public static function types(): array
    {
        return [
            'Especializado' => 'Especializado',
            'Profissional' => 'Profissional',
            'Livre' => 'Livre',
        ];
    }

    protected $fillable = [
        'name',
        'type',
    ];

    public function subjects()
    {
        return $this->hasMany(CourseSubject::class, 'id_course');
    }

    public function subjectsPerSchoolYear($schoolyearId)
    {
        return $this->courseSubjects()
            ->where('id_schoolyear', $schoolyearId)
            ->with('subject')
            ->get()
            ->pluck('subject');
    }
}
