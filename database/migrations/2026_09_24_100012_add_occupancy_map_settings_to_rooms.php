<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->boolean('show_on_occupancy_map')->default(true)->after('description');
        });

        Schema::create('room_features', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('room_feature', function (Blueprint $table): void {
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('room_feature_id')->constrained('room_features')->restrictOnDelete();
            $table->primary(['room_id', 'room_feature_id']);
        });

        DB::table('room_features')->insert([
            ['name' => 'Piano', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cravo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Órgão', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Harpa', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sala de teoria', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_feature');
        Schema::dropIfExists('room_features');
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('show_on_occupancy_map'));
    }
};
