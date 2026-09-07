<?php

namespace App\Helpers;

use App\Models\Schedule;
use App\Models\ScheduleRequest;
use Illuminate\Support\Facades\Auth;
use App\Helpers\DatabaseHelper as DBHelper;

class ScheduleRequestQueueHelper
{
    public static function getUltimoScheduleNoSlot(
        int $weekdayId,
        int $timePeriodId,
        int $roomId,
        int $schoolYearId,
    ): ?Schedule
    {
        return Schedule::where('id_weekday', $weekdayId)
            ->where('id_timeperiod', $timePeriodId)
            ->where('id_room', $roomId)
            ->where('id_schoolyear', $schoolYearId)
            ->whereIn('status', ['Aprovado', 'Pendente'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    public static function criarHorarioERequest(array $dados, string $justificacao): ScheduleRequest
    {
        $schoolYearId = DBHelper::getIDActiveSchoolyear();
        $ultimoHorario = self::getUltimoScheduleNoSlot(
            $dados['id_weekday'],
            $dados['id_timeperiod'],
            $dados['id_room'],
            $schoolYearId,
        );

        if (!$ultimoHorario) {
            throw new \Exception('Não foi encontrado horário válido neste slot.');
        }

        // Cria novo horário com status pendente
        $novoHorario = Schedule::create([
            ...$dados,
            'status' => 'Pendente',
            'id_teacher' => Auth::user()->teacher->id,
            'id_schoolyear' => $schoolYearId,
        ]);

        $pedido = ScheduleRequest::create([
            'id_schedule' => $ultimoHorario->id,
            'id_new_schedule' => $novoHorario->id,
            'id_teacher' => $ultimoHorario->id_teacher,
            'id_teacher_requester' => Auth::user()->teacher->id,
            'id_schoolyear' => $ultimoHorario->id_schoolyear,
            'status' => 'Pendente',
            'justification' => $justificacao,
        ]);

        return $pedido;
    }

    public static function isFirstPending(int $scheduleId, int $requestId): bool
    {
        return (int) ScheduleRequest::where('id_schedule', $scheduleId)
            ->where('status', 'Pendente')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id') === $requestId;
    }

}
