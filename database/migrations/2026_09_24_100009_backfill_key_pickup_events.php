<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('key_controls')->whereNotNull('picked_up_at')->orderBy('id')->each(function (object $record): void {
            $rootId = $record->original_key_control_id ?: $record->id;

            if (DB::table('key_control_events')->where('key_control_id', $rootId)->where('event_type', 'key_picked_up')->exists()) {
                return;
            }

            DB::table('key_control_events')->insert([
                'key_control_id' => $rootId,
                'event_type' => 'key_picked_up',
                'occurred_at' => $record->picked_up_at,
                'performed_by' => $record->picked_up_by,
                'data' => json_encode(['legacy_backfill' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('key_control_events')
            ->where('event_type', 'key_picked_up')
            ->whereJsonContains('data->legacy_backfill', true)
            ->delete();
    }
};
