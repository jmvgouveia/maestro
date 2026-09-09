<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Teacher extends Model
{
    protected $fillable = [
        'number',
        'name',
        'acronym',
        'birthdate',
        'startingdate',
        'id_nationality',
        'id_gender',
        'id_qualification',
        'id_department',
        'id_professionalrelationship',
        'id_contractualrelationship',
        'id_salaryscale',
        'id_user',
    ];

    public function nationality()
    {
        return $this->belongsTo(Nationality::class, 'id_nationality');
    }

    public function gender()
    {
        return $this->belongsTo(Gender::class, 'id_gender');
    }

    public function qualification()
    {
        return $this->belongsTo(Qualification::class, 'id_qualification');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'id_department');
    }

    public function professionalrelationship()
    {
        return $this->belongsTo(ProfessionalRelationship::class, 'id_professionalrelationship');
    }

    public function contractualrelationship()
    {
        return $this->belongsTo(ContratualRelationship::class, 'id_contractualrelationship');
    }

    public function salaryscale()
    {
        return $this->belongsTo(SalaryScale::class, 'id_salaryscale');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects', 'id_teacher', 'id_subject')
            ->withPivot('id_schoolyear');
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class, 'teacher_positions', 'id_teacher', 'id_position')->withPivot('id_schoolyear');
    }

    public function coordinatorBuildings()
    {
        return $this->belongsToMany(Building::class, 'teacher_coordinator_buildings', 'id_teacher', 'id_building')
            ->withPivot('id_schoolyear')
            ->withTimestamps();
    }

    public function timeReductions()
    {
        return $this->belongsToMany(TimeReduction::class, 'teacher_time_reductions', 'id_teacher', 'id_time_reduction')
            ->withPivot('id_schoolyear');
    }

    public function hourCounter()
    {
        return $this->hasOne(TeacherHourCounter::class, 'id_teacher');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'id_teacher');
    }

    public function updateHourCounterFromReductions(?int $schoolYearId = null): void
    {

        // $schoolYearId ??= SchoolYear::where('active', true)->value('id');

        // $totalReduction = $this->positions->sum('reduction_l')
        //     + $this->timeReductions->sum('value_l');

        // DB::table('teacher_hour_counters')
        //     ->where('id_teacher', $this->id)
        //     ->where('id_schoolyear', $schoolYearId)
        //     ->update([
        //         'teaching_load' => DB::raw("teaching_load - {$totalReduction}")
        //     ]);

        $schoolYearId ??= SchoolYear::where('active', true)->value('id');

        $totalReduction = $this->positions->sum('reduction_l')
            + $this->timeReductions->sum('value_l');

        $baseLoad = DB::table('teacher_hour_counters')
            ->where('id_teacher', $this->id)
            ->where('id_schoolyear', $schoolYearId)
            ->value('workload');

        if ($baseLoad !== null) {
            DB::table('teacher_hour_counters')
                ->where('id_teacher', $this->id)
                ->where('id_schoolyear', $schoolYearId)
                ->update([
                    'teaching_load' => 22 - $totalReduction,
                ]);
        }
    }

    protected $casts = [
        'birthdate' => 'date:Y-m-d',
        'startingdate' => 'date:Y-m-d',
    ];

    // protected static function booted()
    // {
    //     static::created(function (Teacher $teacher) {
    //         $schoolYearId = request('id_schoolyear') ?? null;

    //         if ($schoolYearId) {
    //             \App\Models\TeacherHourCounter::firstOrCreate(
    //                 [
    //                     'id_teacher'    => $teacher->id,
    //                     'id_schoolyear' => $schoolYearId,
    //                 ],
    //                 [
    //                     'workload'            => 26,
    //                     'teaching_load'       => 22,
    //                     'non_teaching_load'   => 4,
    //                     'authorized_overtime' => 0,
    //                 ]
    //             );
    //         }
    //     });
    // }
}
