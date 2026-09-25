<?php

namespace App\Services;

use App\Models\KeyControl;
use App\Models\KeyControlEvent;
use App\Models\KeyControlFloorKeyAccess;
use App\Models\KeyControlReportRecipient;
use App\Models\KeyControlSetting;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\KeyControlReleasedNotification;
use App\Notifications\KeyControlDailySummaryNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KeyControlClosureService
{
    public const DAILY_REPORT_TYPE = 'key_control_daily_closure';

    public function releaseRoom(KeyControl $keyControl, User $porter): KeyControl
    {
        $released = DB::transaction(function () use ($keyControl, $porter): KeyControl {
            $record = KeyControl::query()->lockForUpdate()->findOrFail($keyControl->getKey());

            if (! $record->isActive() || $record->holder_type !== Teacher::class) {
                throw new RuntimeException('Esta sala não tem uma chave de professor operacional para libertar.');
            }

            $record->forceFill([
                'room_released_at' => now(),
                'room_released_by' => $porter->getKey(),
                'release_type' => KeyControl::RELEASE_TYPE_MANUAL,
                'release_reason' => 'Chave não devolvida pelo professor.',
            ])->save();

            KeyControlEvent::log(
                $record->originalEventKeyControlId(),
                KeyControlEvent::ROOM_RELEASED,
                $porter->getKey(),
                ['release_type' => $record->release_type, 'reason' => $record->release_reason]
            );

            return $record->load(['room', 'holder.user']);
        });

        $this->notifyTeacher($released);

        return $released;
    }

    public function recordFloorKeyAccess(KeyControl $keyControl, User $porter, Teacher $occupant): KeyControlFloorKeyAccess
    {
        return DB::transaction(function () use ($keyControl, $porter, $occupant): KeyControlFloorKeyAccess {
            $record = KeyControl::query()->lockForUpdate()->findOrFail($keyControl->getKey());

            if (! $record->isRoomReleased() || $record->returned_at !== null) {
                throw new RuntimeException('A sala ainda não foi libertada por chave não devolvida.');
            }

            if (KeyControlFloorKeyAccess::query()->where('room_id', $record->room_id)->whereNull('ended_at')->exists()) {
                throw new RuntimeException('Esta sala já está ocupada através da chave do funcionário de piso.');
            }

            $access = KeyControlFloorKeyAccess::create([
                'key_control_id' => $record->getKey(),
                'room_id' => $record->room_id,
                'accessed_at' => now(),
                'accessed_by' => $porter->getKey(),
                'occupant_type' => Teacher::class,
                'occupant_id' => $occupant->getKey(),
                'reason' => 'Abertura com chave do funcionário de piso após chave não devolvida.',
            ]);

            KeyControlEvent::log(
                $record->originalEventKeyControlId(),
                KeyControlEvent::FLOOR_KEY_OPENED,
                $porter->getKey(),
                ['occupant_type' => Teacher::class, 'occupant_id' => $occupant->getKey(), 'occupant_name' => $occupant->name],
                $access->getKey(),
                $access->accessed_at
            );

            return $access;
        });
    }

    public function endFloorKeyAccess(KeyControlFloorKeyAccess $access, User $porter): KeyControlFloorKeyAccess
    {
        return DB::transaction(function () use ($access, $porter): KeyControlFloorKeyAccess {
            $record = KeyControlFloorKeyAccess::query()->lockForUpdate()->findOrFail($access->getKey());

            if (! $record->isActive()) {
                throw new RuntimeException('A utilização desta sala já foi terminada.');
            }

            $before = KeyControlEvent::snapshotFloorKeyAccess($record);
            $record->forceFill([
                'ended_at' => now(),
                'ended_by' => $porter->getKey(),
            ])->save();

            KeyControlEvent::log(
                $record->keyControl?->originalEventKeyControlId() ?? $record->key_control_id,
                KeyControlEvent::FLOOR_USE_ENDED,
                $porter->getKey(),
                [
                    'occupant_type' => $record->occupant_type,
                    'occupant_id' => $record->occupant_id,
                    'occupant_name' => $record->occupant?->name,
                    'before' => $before,
                    'after' => KeyControlEvent::snapshotFloorKeyAccess($record),
                ],
                $record->getKey(),
                $record->ended_at
            );

            return $record;
        });
    }

    private function notifyTeacher(KeyControl $keyControl): void
    {
        $teacher = $keyControl->holder;
        $user = $teacher instanceof Teacher ? $teacher->user : null;

        if (! $user || blank($user->email) || $keyControl->individual_notification_sent_at !== null) {
            return;
        }

        $user->notify(new KeyControlReleasedNotification($keyControl));
        $keyControl->forceFill(['individual_notification_sent_at' => now()])->saveQuietly();
    }

    public function dailyClosure(): int
    {
        $records = DB::transaction(function (): array {
            $records = KeyControl::query()
                ->with(['room', 'holder.user'])
                ->whereNull('returned_at')
                ->whereNull('room_released_at')
                ->where('is_corrected', false)
                ->lockForUpdate()
                ->get();

            foreach ($records as $record) {
                $record->forceFill([
                    'room_released_at' => now(),
                    'release_type' => KeyControl::RELEASE_TYPE_DAILY_CLOSURE,
                    'release_reason' => 'Fecho diário automático: chave não devolvida.',
                ])->save();

                KeyControlEvent::log(
                    $record->originalEventKeyControlId(),
                    KeyControlEvent::ROOM_RELEASED,
                    null,
                    ['release_type' => $record->release_type, 'reason' => $record->release_reason]
                );
            }

            $floorAccesses = KeyControlFloorKeyAccess::query()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get();

            foreach ($floorAccesses as $access) {
                $before = KeyControlEvent::snapshotFloorKeyAccess($access);
                $access->forceFill([
                    'ended_at' => now(),
                    'ended_by' => null,
                ])->save();

                KeyControlEvent::log(
                    $access->keyControl?->originalEventKeyControlId() ?? $access->key_control_id,
                    KeyControlEvent::FLOOR_USE_ENDED,
                    null,
                    [
                        'occupant_type' => $access->occupant_type,
                        'occupant_id' => $access->occupant_id,
                        'occupant_name' => $access->occupant?->name,
                        'before' => $before,
                        'after' => KeyControlEvent::snapshotFloorKeyAccess($access),
                        'reason' => 'Fecho diário automático.',
                    ],
                    $access->getKey(),
                    $access->ended_at
                );
            }

            return $records->all();
        });

        foreach ($records as $record) {
            $this->notifyTeacher($record);
        }

        $summaryRecords = KeyControl::query()
            ->with(['room', 'holder.user'])
            ->whereDate('room_released_at', today())
            ->whereNull('returned_at')
            ->whereNull('included_in_summary_at')
            ->where('is_corrected', false)
            ->get()
            ->all();

        if ($summaryRecords !== []) {
            $this->sendDailySummary($summaryRecords);
        }

        return count($records);
    }

    private function sendDailySummary(array $records): void
    {
        $recipients = KeyControlReportRecipient::query()
            ->with('user')
            ->where('is_active', true)
            ->get()
            ->filter(fn (KeyControlReportRecipient $recipient): bool => in_array(self::DAILY_REPORT_TYPE, $recipient->report_types ?? [], true))
            ->map(fn (KeyControlReportRecipient $recipient): ?array => $recipient->emailAddress() ? ['email' => $recipient->emailAddress(), 'name' => $recipient->displayName()] : null)
            ->filter()
            ->unique('email');

        foreach ($recipients as $recipient) {
            $route = $recipient['name'] ? [$recipient['email'] => $recipient['name']] : $recipient['email'];
            (new \Illuminate\Notifications\AnonymousNotifiable())->route('mail', $route)->notify(new KeyControlDailySummaryNotification($records));
        }

        KeyControl::query()->whereKey(collect($records)->pluck('id'))->update(['included_in_summary_at' => now()->toDateString()]);
    }
}
