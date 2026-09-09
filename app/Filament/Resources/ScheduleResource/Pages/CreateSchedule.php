<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\Concerns\RedirectsToList;
use App\Filament\Resources\ScheduleResource;
use App\Filament\Resources\ScheduleResource\Traits\CheckScheduleWindow;
use App\Filament\Resources\ScheduleResource\Traits\ChecksScheduleConflicts;
use App\Filament\Resources\ScheduleResource\Traits\HandlesScheduleSwap;
use App\Filament\Resources\ScheduleResource\Traits\HourCounter;
use App\Filament\Resources\ScheduleResource\Traits\ValidatesClassBuildings;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    use CheckScheduleWindow, ChecksScheduleConflicts, HandlesScheduleSwap, HourCounter, InteractsWithActions, ValidatesClassBuildings;
    use RedirectsToList;

    protected $listeners = ['botaoSolicitarTrocaClicado' => 'onSolicitarTrocaClicado'];

    public ?string $justification = null;

    public ?Schedule $conflictingSchedule = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $activeYear = SchoolYear::where('active', true)->first();
        if ($activeYear) {
            $data['id_schoolyear'] = $activeYear->id;
        }

        $teacher = Teacher::where('id_user', Filament::auth()->id())->first();
        if ($teacher) {
            $data['id_teacher'] = $teacher->id;
        }

        $data['status'] = 'Aprovado';

        return $data;
    }

    protected function beforeCreate(): void
    {
        DB::transaction(function () {
            $this->validateScheduleWindow($this->data['id_classes'] ?? []);
            $this->checkScheduleConflictsAndAvailability($this->data);
            $this->validateSelectedClassesBelongToRoomBuilding(
                $this->data['id_classes'] ?? [],
                $this->data['id_room'] ?? null,
            );
        });
    }

    protected function beforeSave(): void
    {
        $this->validateScheduleWindow($this->data['id_classes'] ?? []);
        $this->checkScheduleConflictsAndAvailability($this->data, $this->record->id);
    }

    protected function afterCreate(): void
    {

        try {
            DB::transaction(function () {
                $this->record->classes()->sync($this->data['id_classes'] ?? []);
                $this->record->students()->sync($this->data['students'] ?? []);
                // ScheduleResource::hoursCounterUpdate($this->record, false);
                $this->hoursCounterUpdate($this->record, false);
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao criar o horário')
                ->body($e->getMessage())
                ->danger()
                ->send();
            throw $e;
        }
    }

    public function mount(): void
    {
        $this->form->fill([
            'id_weekday' => request('weekday'),
            'id_timeperiod' => request('timeperiod'),
        ]);
    }
}
