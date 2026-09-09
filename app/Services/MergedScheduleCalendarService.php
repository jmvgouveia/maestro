<?php

// app/Services/MergedScheduleCalendarService.php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\Timeperiod;
use App\Models\Weekday;
use Illuminate\Support\Facades\Schema;

class MergedScheduleCalendarService
{
    public static function buildForTeachers(array $teacherIds, array $buildingScopes = []): array
    {
        $teacherIds = array_values(array_unique(array_filter($teacherIds, fn ($v) => ! empty($v))));

        // Descobrir nomes de colunas reais
        $table = (new Schedule)->getTable();
        $colTeacher = Schema::hasColumn($table, 'teacher_id') ? 'teacher_id' : 'id_teacher';
        $colWeekday = Schema::hasColumn($table, 'weekday_id') ? 'weekday_id' : 'id_weekday';
        $colTimeperiod = Schema::hasColumn($table, 'timeperiod_id') ? 'timeperiod_id' : 'id_timeperiod';

        // Dados base
        $weekdays = Weekday::query()->orderBy('id')->pluck('weekday', 'id')->toArray();
        $timePeriods = Timeperiod::query()->orderBy('start_time')->get();
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');

        // Eager load mínimo e seguro (usa nomes de relações já criadas no modelo)
        $with = array_values(array_intersect(
            ['timeperiod', 'weekday', 'teacher', 'subject', 'room'],
            // só carrega as relações que de facto existem como métodos
            collect(get_class_methods(Schedule::class))->filter()->all()
        ));

        $schedules = collect();
        if ($activeSchoolYearId) {
            $query = Schedule::query()
                ->with($with)
                ->whereIn($colTeacher, $teacherIds)
                ->where('id_schoolyear', $activeSchoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP']);

            if ($buildingScopes !== []) {
                $query->where(function ($scopeQuery) use ($teacherIds, $buildingScopes, $colTeacher): void {
                    foreach ($teacherIds as $teacherId) {
                        $scopeQuery->orWhere(function ($teacherQuery) use ($teacherId, $buildingScopes, $colTeacher): void {
                            $teacherQuery->where($colTeacher, $teacherId);
                            $buildings = array_key_exists($teacherId, $buildingScopes)
                                ? $buildingScopes[$teacherId]
                                : [];

                            if ($buildings !== null) {
                                $teacherQuery->whereHas('room', fn ($roomQuery) => $roomQuery->whereIn('id_building', $buildings));
                            }
                        });
                    }
                });
            }

            $schedules = $query->get();
        }

        // Montar calendar[timeperiod_id][weekday_id] = [schedules...]
        $calendar = [];
        foreach ($schedules as $s) {
            $tp = $s->{$colTimeperiod};
            $wd = $s->{$colWeekday};
            $calendar[$tp][$wd] ??= [];
            $calendar[$tp][$wd][] = $s;
        }

        // Paleta de cores por docente
        $teachers = Teacher::whereIn('id', $teacherIds)->orderBy('name')->get(['id', 'name', 'number', 'acronym']);
        $teacherPalette = [];
        foreach ($teachers as $t) {
            $teacherPalette[$t->id] = self::hslFromId($t->id);
        }

        // compat: arrays vazios para badges (se não usados, ignore)
        $recusados = $PedidosAprovadosDP = $escalados = [];

        return compact('weekdays', 'timePeriods', 'calendar', 'teacherPalette', 'teachers', 'recusados', 'PedidosAprovadosDP', 'escalados');
    }

    protected static function hslFromId(int $id): string
    {
        $h = ($id * 47) % 360;

        return "hsl({$h} 70% 45%)";
    }
}
