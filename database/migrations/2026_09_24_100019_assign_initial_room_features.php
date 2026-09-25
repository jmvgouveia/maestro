<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = DB::table('room_features')->pluck('id', 'name');
        $buildingId = DB::table('buildings')->where('name', 'SEDE')->value('id');

        if (! $buildingId) {
            return;
        }

        $assign = function (string $feature, array $roomNumbers) use ($features, $buildingId): void {
            $featureId = $features[$feature] ?? null;

            if (! $featureId) {
                return;
            }

            $roomIds = DB::table('rooms')
                ->where('id_building', $buildingId)
                ->whereIn('name', collect($roomNumbers)->map(fn (int|string $number): string => 'Sala '.$number)->all())
                ->pluck('id');

            foreach ($roomIds as $roomId) {
                DB::table('room_feature')->insertOrIgnore([
                    'room_id' => $roomId,
                    'room_feature_id' => $featureId,
                ]);
            }
        };

        $assign('Piano', [101, 103, 105, 107, 109, 111, 112, 113, 114, 201, 202, 206, 207, 209, 210, 211, 212, 213, 214, 215, 301, 303, 304, 309, 310, 311, 312, 313, 314, 315]);
        $assign('Cravo', [203]);
        $assign('Órgão', [108]);
        $assign('Harpa', [106]);
        $assign('Sala de teoria', [202, 205, 206, 208, 211, 314]);
    }

    public function down(): void
    {
        $featureIds = DB::table('room_features')->whereIn('name', ['Piano', 'Cravo', 'Órgão', 'Harpa', 'Sala de teoria'])->pluck('id');
        DB::table('room_feature')->whereIn('room_feature_id', $featureIds)->delete();
    }
};
