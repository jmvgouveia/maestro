<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_control_floor_key_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_control_id')->constrained('key_controls');
            $table->foreignId('room_id')->constrained('rooms');
            $table->timestamp('accessed_at');
            $table->foreignId('accessed_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['key_control_id']);
            $table->index(['room_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_control_floor_key_accesses');
    }
};
