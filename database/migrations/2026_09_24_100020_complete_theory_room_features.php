<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $featureId = DB::table('room_features')->where('name', 'Sala de teoria')->value('id');
        $buildingId = DB::table('buildings')->where('name', 'SEDE')->value('id');

        if (! $featureId || ! $buildingId) {
            return;
        }

        $roomIds = DB::table('rooms')
            ->where('id_building', $buildingId)
            ->whereIn('name', ['Sala 202', 'Sala 205', 'Sala 206', 'Sala 208', 'Sala 211', 'Sala 314'])
            ->pluck('id');

        foreach ($roomIds as $roomId) {
            if (! DB::table('room_feature')->where('room_id', $roomId)->where('room_feature_id', $featureId)->exists()) {
                DB::table('room_feature')->insert([
                    'room_id' => $roomId,
                    'room_feature_id' => $featureId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $featureId = DB::table('room_features')->where('name', 'Sala de teoria')->value('id');
        $roomIds = DB::table('rooms')->whereIn('name', ['Sala 202', 'Sala 205', 'Sala 206', 'Sala 208', 'Sala 211', 'Sala 314'])->pluck('id');

        DB::table('room_feature')->where('room_feature_id', $featureId)->whereIn('room_id', $roomIds)->delete();
    }
};
