<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('key_controls')->orderBy('id')->each(function (object $record): void {
            $rootId = $record->original_key_control_id ?: $record->id;

            if ($record->room_released_at !== null && ! DB::table('key_control_events')
                ->where('key_control_id', $rootId)
                ->where('event_type', 'room_released')
                ->exists()) {
                DB::table('key_control_events')->insert([
                    'key_control_id' => $rootId,
                    'event_type' => 'room_released',
                    'occurred_at' => $record->room_released_at,
                    'performed_by' => $record->room_released_by,
                    'data' => json_encode([
                        'release_type' => $record->release_type,
                        'reason' => $record->release_reason,
                        'legacy_backfill' => true,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($record->returned_at !== null && $record->room_released_at !== null && ! DB::table('key_control_events')
                ->where('key_control_id', $rootId)
                ->where('event_type', 'key_returned')
                ->exists()) {
                DB::table('key_control_events')->insert([
                    'key_control_id' => $rootId,
                    'event_type' => 'key_returned',
                    'occurred_at' => $record->returned_at,
                    'performed_by' => $record->returned_by,
                    'data' => json_encode(['legacy_backfill' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        DB::table('key_control_floor_key_accesses')->orderBy('id')->each(function (object $access): void {
            $rootId = DB::table('key_controls')->where('id', $access->key_control_id)->value('original_key_control_id')
                ?: $access->key_control_id;

            DB::table('key_control_events')->insertOrIgnore([
                'key_control_id' => $rootId,
                'floor_key_access_id' => $access->id,
                'event_type' => 'floor_key_opened',
                'occurred_at' => $access->accessed_at,
                'performed_by' => $access->accessed_by,
                'data' => json_encode(['legacy_backfill' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($access->ended_at !== null) {
                DB::table('key_control_events')->insert([
                    'key_control_id' => $rootId,
                    'floor_key_access_id' => $access->id,
                    'event_type' => 'floor_use_ended',
                    'occurred_at' => $access->ended_at,
                    'performed_by' => $access->ended_by,
                    'data' => json_encode(['legacy_backfill' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('key_control_events')->whereJsonContains('data->legacy_backfill', true)->delete();
    }
};
