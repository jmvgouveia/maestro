<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_building_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('building_id')->constrained('buildings');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['user_id', 'building_id']);
            $table->index('building_id');
        });

        if (Schema::hasTable('user_room_authorizations')) {
            DB::table('user_room_authorizations as room_authorizations')
                ->join('rooms', 'rooms.id', '=', 'room_authorizations.room_id')
                ->select('room_authorizations.user_id', 'rooms.id_building as building_id', 'room_authorizations.created_by')
                ->distinct()
                ->orderBy('room_authorizations.id')
                ->get()
                ->each(function (object $authorization): void {
                    DB::table('user_building_authorizations')->insertOrIgnore([
                        'user_id' => $authorization->user_id,
                        'building_id' => $authorization->building_id,
                        'created_by' => $authorization->created_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_building_authorizations');
    }
};
