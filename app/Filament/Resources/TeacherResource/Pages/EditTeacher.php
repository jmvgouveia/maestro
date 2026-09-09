<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\Concerns\RedirectsToList;
use App\Filament\Resources\TeacherResource;
use App\Models\SchoolYear;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    use RedirectsToList;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $teacher = $this->record->load('user');

        $data['user']['email'] = $teacher->user->email ?? '';
        $data['coordinator_buildings'] = $teacher->coordinatorBuildings()
            ->wherePivot('id_schoolyear', SchoolYear::query()->where('active', true)->value('id'))
            ->pluck('buildings.id')
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record->user) {
            $record->user->name = $data['name'];

            if (! auth()->user()?->hasRole('Recursos Humanos')) {
                $record->user->email = $data['user']['email'];
            }

            if (! empty($data['user']['password'])) {
                $record->user->password = Hash::make($data['user']['password']);
            }

            $record->user->saveOrFail();
        }

        $coordinatorBuildingIds = $data['coordinator_buildings'] ?? [];
        $positionsSubmitted = array_key_exists('positions', $data);
        $positionIds = $data['positions'] ?? [];
        unset($data['user'], $data['coordinator_buildings']);

        $record->updateOrFail($data);

        $record->load(['positions', 'timeReductions']);

        $schoolYearId = SchoolYear::where('active', true)->value('id');

        foreach ($record->positions as $position) {
            DB::table('teacher_positions')
                ->where('id_teacher', $record->id)
                ->where('id_position', $position->id)
                ->update(['id_schoolyear' => $schoolYearId]);
        }

        foreach ($record->timeReductions as $reduction) {
            DB::table('teacher_time_reductions')
                ->where('id_teacher', $record->id)
                ->where('id_time_reduction', $reduction->id)
                ->update(['id_schoolyear' => $schoolYearId]);
        }

        $this->syncCoordinatorBuildings($record, $coordinatorBuildingIds, $positionIds, $positionsSubmitted, $schoolYearId);

        $record->loadMissing(['positions', 'timeReductions']); // garante que relações estão atualizadas
        $record->updateHourCounterFromReductions($schoolYearId);

        return $record;
    }

    private function syncCoordinatorBuildings(Model $teacher, array $buildingIds, array $positionIds, bool $positionsSubmitted, ?int $schoolYearId): void
    {
        if (! $positionsSubmitted) {
            return;
        }

        DB::table('teacher_coordinator_buildings')
            ->where('id_teacher', $teacher->id)
            ->where('id_schoolyear', $schoolYearId)
            ->delete();

        if (! $schoolYearId || ! TeacherResource::hasBuildingCoordinatorPosition($positionIds)) {
            return;
        }

        $rows = collect($buildingIds)->filter()->unique()->map(fn ($buildingId): array => [
            'id_teacher' => $teacher->id,
            'id_building' => (int) $buildingId,
            'id_schoolyear' => $schoolYearId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows !== []) {
            DB::table('teacher_coordinator_buildings')->insert($rows);
        }
    }
}
