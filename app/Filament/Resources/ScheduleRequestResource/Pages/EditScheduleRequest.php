<?php

namespace App\Filament\Resources\ScheduleRequestResource\Pages;

use App\Filament\Resources\Concerns\RedirectsToList;
use App\Filament\Resources\ScheduleRequestResource;
use App\Filament\Resources\ScheduleResource\Traits\CheckScheduleWindow;
use App\Filament\Resources\ScheduleResource\Traits\HourCounter;
use App\Helpers\DatabaseHelper as DBHelper;
use App\Helpers\MensagensErro as MSGErro;
use App\Helpers\ScheduleRequestQueueHelper;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditScheduleRequest extends EditRecord
{
    protected static string $resource = ScheduleRequestResource::class;

    use CheckScheduleWindow, HourCounter;
    use RedirectsToList;

    protected function getFormActions(): array
    {
        $this->record->loadMissing('scheduleConflict');

        $teacherId = Filament::auth()->user()?->teacher?->id;
        $requesterId = $this->record->id_teacher_requester;
        $conflictOwnerId = $this->record->scheduleConflict?->id_teacher;

        $isRequestOwner = $teacherId === $requesterId;
        $isReceiver = $teacherId === $conflictOwnerId;
        $isGestor = Filament::auth()->user()?->hasRole('Gestor Conflitos') ?? false;
        $isFirstPending = ScheduleRequestQueueHelper::isFirstPending($this->record->id_schedule, $this->record->id);

        $status = $this->record->status;
        $actions = [];

        // Aprovar troca
        if (($isReceiver || $isGestor)
            && ($status !== 'Pendente' || $isFirstPending)
            && $status !== 'Recusado'
            && $status !== 'Aprovado DP'
            && $status !== 'Aprovado') {
            $actions[] = Action::make('accept')
                ->label('Aceitar Troca')
                ->color('success')

                ->mountUsing(function () {
                    if ($this->record->status === 'Eliminado') {
                        Notification::make()
                            ->title('Pedido já eliminado')
                            ->body('Este pedido foi eliminado por outro utilizador.')
                            ->danger()
                            ->send();

                        redirect(request()->header('Referer') ?? url()->previous() ?? filament()->getUrl());
                    }
                })
                ->visible(fn () => $this->record->status !== 'Eliminado')
                ->form([

                    Select::make('id_room_novo')
                        ->label('Sala Nova')
                        ->required()
                        ->options(fn () => $this->getAvailableRooms()),
                    Textarea::make('response')
                        ->label($isGestor ? 'Justificação da Aprovação (Gestor)' : 'Justificação da Aceitação')
                        ->required(),
                ])

                ->action(function (array $data) {
                    $isGestor = Filament::auth()->user()?->hasRole('Gestor Conflitos') ?? false;

                    if ($this->record->status === 'Eliminado') {

                        Notification::make()
                            ->title('Pedido já eliminado')
                            ->body('Este pedido já foi eliminado por outro utilizador.')
                            ->danger()
                            ->send();

                        return redirect(filament()->getUrl());
                    }

                    DB::transaction(function () use ($data, $isGestor) {
                        $this->validateScheduleWindow(scheduleIds: [
                            $this->record->id_schedule,
                            $this->record->id_new_schedule,
                        ]);

                        // $this->record->update([
                        //     'status' => $isGestor ? 'Aprovado DP' : 'Aprovado',
                        //     'response' => $data['response'],
                        // ]);

                        // DBHelper::updateScheduleRequestStatus($this->record->id, false, $isGestor ? 'Aprovado DP' : 'Aprovado', MSGErro::ERRO_APROVAR_SCHEDULE);

                        DBHelper::updateScheduleRequestData(
                            $this->record->id,
                            [
                                'response' => $data['response'],
                                'status' => $isGestor ? 'Aprovado DP' : 'Aprovado',
                                'responded_at' => now(),
                            ],
                            MSGErro::ERRO_APROVAR_SCHEDULE
                        );

                        // $this->record->scheduleConflict?->update([
                        //     'status' => $isGestor ? 'Aprovado DP' : 'Aprovado',
                        //     'id_room' => $data['id_room_novo'],
                        //     'responded_at' => now(),
                        // ]);

                        DBHelper::updateScheduleData(
                            $this->record->scheduleConflict->id,
                            [
                                'status' => $isGestor ? 'Aprovado DP' : 'Aprovado',
                                'id_room' => $data['id_room_novo'],

                            ],
                            MSGErro::ERRO_APROVAR_SCHEDULE
                        );

                        DBHelper::updateScheduleData(
                            $this->record->scheduleNew?->id,
                            [
                                'status' => $isGestor ? 'Aprovado DP' : 'Aprovado',
                            ],
                            MSGErro::ERRO_APROVAR_SCHEDULE
                        );

                        // $this->record->scheduleNew?->update(['status' => 'Aprovado']);
                        //  DBHelper::updateScheduleRequestStatus($this->record->scheduleNew?->id, true, 'Aprovado', MSGErro::ERRO_APROVAR_SCHEDULE);

                        $this->hoursCounterUpdate($this->record->scheduleNew, false);
                        $this->hoursCounterUpdate($this->record->scheduleConflict, false);

                        extract($this->getScheduleDetails());

                        Notification::make()
                            ->title('Pedido de troca aprovado com sucesso')
                            ->body("Aprovou o pedido de {$requestername} para trocar a sala {$currentRoom}, no {$dayName} às {$timePeriod}.")
                            ->success()
                            ->send();

                        Notification::make()
                            ->title('Pedido aprovado')
                            ->body("O professor {$ownername} aprovou o seu pedido para a troca da aula na sala {$currentRoom}, no {$dayName} às {$timePeriod}.")
                            ->success()
                            ->actions([
                                NotificationAction::make('Ver Pedido')
                                    ->url(route('filament.admin.resources.schedule-requests.edit', [
                                        'record' => $this->record->getKey(),
                                    ])),
                            ])
                            ->sendToDatabase($requester);
                        // -----------NEW
                    });

                    // return redirect($this->getResource()::getUrl('index'));
                });
        }

        // Recusar troca
        if (($isReceiver || $isGestor) && $status !== 'Aprovado' && $status !== 'Aprovado DP' && $status !== 'Recusado') {
            $actions[] = Action::make('reject')
                ->label('Recusar Troca')
                ->color('danger')
                ->visible(fn () => $this->record->status !== 'Eliminado')
                ->mountUsing(function () {
                    if ($this->record->status === 'Eliminado') {
                        Notification::make()
                            ->title('Pedido já eliminado')
                            ->body('Este pedido foi eliminado por outro utilizador.')
                            ->danger()
                            ->send();

                        // Previne a abertura do modal
                        redirect(request()->header('Referer') ?? url()->previous() ?? filament()->getUrl());
                    }
                })
                ->form([
                    Textarea::make('response')->label('Justificação para Recusa')->required(),
                ])
                ->action(function (array $data) {
                    $isGestor = Filament::auth()->user()?->hasRole('Gestor Conflitos') ?? false;

                    if ($this->record->status === 'Eliminado') {

                        Notification::make()
                            ->title('Pedido já eliminado')
                            ->body('Este pedido já foi eliminado por outro utilizador.')
                            ->danger()
                            ->send();

                        return redirect(filament()->getUrl());
                    }

                    DB::transaction(function () use ($data, $isGestor) {
                        $this->validateScheduleWindow(scheduleIds: [
                            $this->record->id_schedule,
                            $this->record->id_new_schedule,
                        ]);

                        DBHelper::updateScheduleRequestData(
                            $this->record->id,
                            [
                                'status' => $isGestor ? 'Recusado DP' : 'Recusado',
                                'response' => $data['response'],
                                'responded_at' => now(),
                            ],
                            MSGErro::ERRO_APROVAR_SCHEDULE
                        );

                        // $this->record->update([
                        //     'status' => $isGestor ? 'Recusado DP' : 'Recusado',
                        //     'response' => $data['response'],
                        //     'responded_at' => now(),
                        // ]);

                        extract($this->getScheduleDetails());

                        Notification::make()
                            ->title('Pedido de troca recusado')
                            ->body("Recusou o pedido de {$requestername} para a aula na sala {$currentRoom}, no {$dayName} às {$timePeriod}.")
                            ->danger()
                            ->send();

                        Notification::make()
                            ->title('Pedido recusado')
                            ->body("O professor {$ownername} recusou o seu pedido de troca da aula na sala {$currentRoom}, no {$dayName} às {$timePeriod}.")
                            ->danger()
                            ->actions([
                                NotificationAction::make('Ver Pedido')
                                    ->url(route('filament.admin.resources.schedule-requests.edit', [
                                        'record' => $this->record->getKey(),
                                    ])),
                            ])
                            ->sendToDatabase($requester);
                    });

                    // return redirect($this->getResource()::getUrl('index'));
                });
        }

        // Escalar situação
        if (($isRequestOwner || $isGestor) && $status === 'Recusado') {
            $actions[] = Action::make('escalar')
                ->label('Escalar Situação')
                ->visible(fn () => $this->record->status !== 'Eliminado')
                ->color('warning')
                ->mountUsing(function () {
                    if ($this->record->status === 'Eliminado') {
                        Notification::make()
                            ->title('Pedido já eliminado')
                            ->body('Este pedido foi eliminado por outro utilizador.')
                            ->danger()
                            ->send();

                        // Previne a abertura do modal
                        redirect(request()->header('Referer') ?? url()->previous() ?? filament()->getUrl());
                    }
                })
                ->form([
                    Textarea::make('scaled_justification')->label('Justificação')->required(),
                ])
                ->action(function (array $data) {

                    if ($this->record->status === 'Eliminado') {

                        Notification::make()
                            ->title('Pedido já eliminado')
                            ->body('Este pedido já foi eliminado por outro utilizador.')
                            ->danger()
                            ->send();

                        return redirect(filament()->getUrl());
                    }
                    DB::transaction(function () use ($data) {
                        $this->validateScheduleWindow(scheduleIds: [
                            $this->record->id_schedule,
                            $this->record->id_new_schedule,
                        ]);

                        DBHelper::updateScheduleRequestData(
                            $this->record->id,
                            [
                                'status' => 'Escalado',
                                'scaled_justification' => $data['scaled_justification'],
                                'justification_at' => now(),
                            ],
                            MSGErro::ERRO_APROVAR_SCHEDULE
                        );

                        // $this->record->update([
                        //     'status' => 'Escalado',
                        //     'scaled_justification' => $data['scaled_justification'],
                        //     'justification_at' => now(),
                        // ]);

                        extract($this->getScheduleDetails());

                        Notification::make()
                            ->title('Pedido Escalado')
                            ->warning()
                            ->body('O pedido foi escalado para análise superior.')
                            ->send();

                        Notification::make()
                            ->title('Pedido de troca escalado')
                            ->body("O professor {$requestername} escalou o pedido de troca da aula na sala {$currentRoom}, no {$dayName} às {$timePeriod}.")
                            ->warning()
                            ->actions([
                                NotificationAction::make('Ver Pedido')
                                    ->url(route('filament.admin.resources.schedule-requests.edit', [
                                        'record' => $this->record->getKey(),
                                    ])),
                            ])
                            ->sendToDatabase($owner);

                        Notification::make()
                            ->title('Pedido escalado')
                            ->body('O seu pedido de troca foi escalado para análise superior.')
                            ->warning()
                            ->actions([
                                NotificationAction::make('Ver Pedido')
                                    ->url(route('filament.admin.resources.schedule-requests.edit', [
                                        'record' => $this->record->getKey(),
                                    ])),
                            ])
                            ->sendToDatabase($requester);
                    });

                    //   return redirect($this->getResource()::getUrl('index'));
                });
        }

        $actions[] = Action::make('cancel')
            ->label('Cancelar')
            ->url($this->getResource()::getUrl('index'))
            ->color('gray');

        $actions[] = DeleteAction::make()
            ->label('Eliminar Horário')
            ->color('danger')
            ->visible(fn () => ! in_array($this->record->status, ['Eliminado', 'Aprovado']))
            ->requiresConfirmation()
            ->action(function () {
                if ($this->record->status === 'Eliminado') {
                    Notification::make()
                        ->title('Pedido já eliminado')
                        ->body('Este pedido já foi eliminado por outro utilizador.')
                        ->danger()
                        ->send();

                    return redirect(filament()->getUrl());
                }

                try {
                    DB::transaction(function () {
                        $user = Filament::auth()->user();
                        $teacherId = $user?->teacher?->id;
                        $isGestor = $user?->hasRole('Gestor Conflitos') ?? false;

                        $scheduleRequest = $this->record->refresh();
                        $scheduleNew = $scheduleRequest->scheduleNew;
                        $scheduleConflict = $scheduleRequest->scheduleConflict;

                        switch ($scheduleRequest->status) {
                            case 'Pendente':
                                if ($teacherId === $scheduleRequest->id_teacher_requester) {

                                    $deletedSchedule = $scheduleNew;

                                    if (in_array($deletedSchedule->status, ['Aprovado', 'Aprovado DP'])) {
                                        $this->hoursCounterUpdate($deletedSchedule, true);
                                    }

                                    DBHelper::updateScheduleData(
                                        $deletedSchedule->id,
                                        [
                                            'status' => 'Eliminado',
                                        ],
                                        MSGErro::ERRO_ELIMINAR_SCHEDULE
                                    );

                                    // $deletedSchedule->update(['status' => 'Eliminado']);

                                    DBHelper::updateScheduleRequestData(
                                        $scheduleRequest->id,
                                        [
                                            'status' => 'Eliminado',
                                        ],
                                        MSGErro::ERRO_ELIMINAR_SCHEDULE
                                    );
                                    // $scheduleRequest->update(['status' => 'Eliminado']);

                                    Notification::make()
                                        ->title('Pedido cancelado')
                                        ->body('Eliminou a sua marcação. O pedido de troca foi cancelado.')
                                        ->success()
                                        ->sendToDatabase($user);
                                } elseif ($teacherId === $scheduleConflict?->id_teacher) {
                                    // Alvo elimina o seu próprio horário
                                    $deletedSchedule = $scheduleConflict;

                                    if (in_array($deletedSchedule->status, ['Aprovado', 'Aprovado DP'])) {
                                        $this->hoursCounterUpdate($deletedSchedule, true);
                                    }

                                    DBHelper::updateScheduleData(
                                        $deletedSchedule->id,
                                        [
                                            'status' => 'Eliminado',
                                        ],
                                        MSGErro::ERRO_ELIMINAR_SCHEDULE
                                    );

                                    // --------NEW

                                    // $deletedSchedule->update(['status' => 'Eliminado']);

                                    if ($scheduleNew->status !== 'Aprovado') {
                                        // $scheduleNew->update([
                                        //     'status' => $isGestor ? 'Aprovado DP' : 'Aprovado',
                                        // ]);

                                        DBHelper::updateScheduleData(
                                            $scheduleNew->id,
                                            [
                                                'status' => $isGestor ? 'Aprovado DP' : 'Aprovado',
                                            ],
                                            MSGErro::ERRO_APROVAR_SCHEDULE
                                        );

                                        $this->hoursCounterUpdate($scheduleNew, false);
                                    }

                                    DBHelper::updateScheduleRequestData(
                                        $scheduleRequest->id,
                                        [
                                            'status' => $isGestor ? 'Eliminado DP' : 'Eliminado',
                                            'responded_at' => now(),
                                        ],
                                        MSGErro::ERRO_ELIMINAR_SCHEDULE
                                    );

                                    // $scheduleRequest->update([
                                    //     'status' => $isGestor ? 'Eliminado DP' : 'Eliminado',
                                    //     'responded_at' => now(),
                                    // ]);

                                    Notification::make()
                                        ->title('Troca aprovada')
                                        ->body('Eliminou a sua marcação. A marcação da outra parte foi automaticamente aprovada.')
                                        ->success()
                                        ->sendToDatabase($user);
                                } else {
                                    throw new \Exception('Não tem permissão para eliminar esta marcação.');
                                }

                                break;

                            case 'Recusado':
                                if ($teacherId === $scheduleRequest->id_teacher_requester) {
                                    $deletedSchedule = $scheduleNew;

                                    if (in_array($deletedSchedule->status, ['Aprovado', 'Aprovado DP'])) {
                                        $this->hoursCounterUpdate($deletedSchedule, true);
                                    }

                                    // $deletedSchedule->update(['status' => 'Eliminado']);
                                    DBHelper::updateScheduleData(
                                        $deletedSchedule->id,
                                        [
                                            'status' => 'Eliminado',
                                        ],
                                        MSGErro::ERRO_ELIMINAR_SCHEDULE
                                    );

                                    DBHelper::updateScheduleRequestData(
                                        $scheduleRequest->id,
                                        [
                                            'status' => 'Eliminado',
                                            'responded_at' => now(),
                                        ],
                                        'Erro ao atualizar pedido recusado'
                                    );
                                    // $scheduleRequest->update([
                                    //     'status' => 'Eliminado',
                                    //     'responded_at' => now(),
                                    // ]);

                                    Notification::make()
                                        ->title('Pedido eliminado')
                                        ->body('Eliminou a sua marcação. O pedido de troca foi encerrado.')
                                        ->success()
                                        ->sendToDatabase($user);
                                    // Professor que fez o pedido
                                } elseif ($teacherId === $scheduleConflict?->id_teacher) {
                                    $deletedSchedule = $scheduleConflict;

                                    if (in_array($deletedSchedule->status, ['Aprovado', 'Aprovado DP'])) {
                                        $this->hoursCounterUpdate($deletedSchedule, true);
                                    }

                                    DBHelper::updateScheduleData(
                                        $deletedSchedule->id,
                                        [
                                            'status' => 'Eliminado',
                                        ],
                                        MSGErro::ERRO_ELIMINAR_SCHEDULE
                                    );
                                    // $deletedSchedule->update(['status' => 'Eliminado']);
                                    // ---new

                                    Notification::make()
                                        ->title('Marcação eliminada')
                                        ->body('Eliminou a sua marcação.')
                                        ->success()
                                        ->sendToDatabase($user);
                                } else {
                                    throw new \Exception('Não tem permissão para eliminar esta marcação.');
                                }
                                break;
                            case 'Aprovado DP':
                                if ($teacherId === $scheduleRequest->id_teacher_requester) {
                                    $deletedSchedule = $scheduleNew;

                                    if (in_array($deletedSchedule->status, ['Aprovado', 'Aprovado DP'])) {
                                        $this->hoursCounterUpdate($deletedSchedule, true);
                                    }

                                    DBHelper::updateScheduleData(
                                        $deletedSchedule->id,
                                        ['status' => 'Eliminado'],
                                        'Erro ao eliminar horário (Aprovado DP - requester)'
                                    );

                                    DBHelper::updateScheduleRequestData(
                                        $scheduleRequest->id,
                                        ['status' => 'Eliminado'],
                                        'Erro ao atualizar pedido (Aprovado DP - requester)'
                                    );

                                    DBHelper::updateScheduleData(
                                        $scheduleConflict->id,
                                        ['status' => 'Aprovado'],
                                        'Erro ao reativar horário em conflito (Aprovado DP - requester)'
                                    );

                                    // $deletedSchedule->update(['status' => 'Eliminado']);

                                    // $scheduleRequest->update(['status' => 'Eliminado']);

                                    // $scheduleConflict->update(['status' => 'Aprovado']);
                                    //          //      $deletedSchedule->update(['status' => 'Aprovado']);

                                    Notification::make()
                                        ->title('Pedido eliminado')
                                        ->body('Eliminou a sua marcação. O pedido de troca foi encerrado.')
                                        ->success()
                                        ->sendToDatabase($user);
                                } elseif ($teacherId === $scheduleConflict?->id_teacher) {
                                    // Alvo elimina o seu próprio horário
                                    $deletedSchedule = $scheduleConflict;

                                    if (in_array($deletedSchedule->status, ['Aprovado', 'Aprovado DP'])) {
                                        $this->hoursCounterUpdate($deletedSchedule, true);
                                    }

                                    DBHelper::updateScheduleData(
                                        $deletedSchedule->id,
                                        ['status' => 'Eliminado'],
                                        'Erro ao eliminar horário (Aprovado DP - conflito)'
                                    );

                                    // --------NEW

                                    //  $deletedSchedule->update(['status' => 'Eliminado']);

                                    if ($scheduleNew->status !== 'Aprovado') {

                                        DBHelper::updateScheduleData(
                                            $scheduleNew->id,
                                            ['status' => 'Aprovado'],
                                            'Erro ao aprovar novo horário (Aprovado DP - conflito)'
                                        );

                                        // $scheduleNew->update([
                                        //     'status' => 'Aprovado',
                                        // ]);
                                        // $this->hoursCounterUpdate($scheduleNew, false); --> VALIDAR CONTADOR DE HORAS
                                    }

                                    DBHelper::updateScheduleRequestData(
                                        $scheduleRequest->id,
                                        ['status' => 'Eliminado'],
                                        'Erro ao atualizar pedido (Aprovado DP - conflito)'
                                    );

                                    // $scheduleRequest->update(['status' => 'Eliminado']);

                                    // $scheduleNew->update(['status' => 'Aprovado']);

                                    Notification::make()
                                        ->title('Troca aprovada')
                                        ->body('Eliminou a sua marcação. A marcação da outra parte foi automaticamente aprovada.')
                                        ->success()
                                        ->sendToDatabase($user);
                                } else {
                                    throw new \Exception('Não tem permissão para eliminar esta marcação.');
                                }

                                break;

                            default:
                                throw new \Exception('Estado ainda não tratado.');
                        }

                        return filament()->getUrl();
                    });
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Erro ao eliminar o horário')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });

        return $actions;
    }

    protected function getAvailableRooms(): array
    {
        $conflict = $this->record->scheduleConflict;

        if (! $conflict) {
            return [];
        }

        return Room::where('id_building', $conflict->room?->id_building)
            ->whereDoesntHave('schedules', function ($query) use ($conflict) {
                $query->where('id_timeperiod', $conflict->id_timeperiod)
                    ->where('id_weekday', $conflict->id_weekday)
                    ->where('status', '!=', 'Eliminado');
            })
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getScheduleDetails(): array
    {
        $conflict = $this->record->scheduleConflict;

        return [
            'requester' => $this->record->requester?->user,
            'requestername' => $this->record->requester?->name ?? 'desconhecido',
            'owner' => $conflict?->teacher?->user,
            'ownername' => $conflict?->teacher?->name ?? 'desconhecido',
            'currentRoom' => $conflict?->room?->name ?? 'desconhecida',
            'dayName' => $conflict?->weekday?->weekday ?? 'desconhecido',
            'timePeriod' => $conflict?->timeperiod?->description ?? 'desconhecido',
        ];
    }
}
