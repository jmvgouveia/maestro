<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms');
            $table->morphs('holder');
            $table->timestamp('picked_up_at');
            $table->timestamp('returned_at')->nullable();
            $table->text('pick_up_observations')->nullable();
            $table->text('return_observations')->nullable();
            $table->foreignId('picked_up_by')->constrained('users');
            $table->foreignId('returned_by')->nullable()->constrained('users');
            $table->foreignId('corrected_by')->nullable()->constrained('users');
            $table->text('correction_reason')->nullable();
            $table->foreignId('original_key_control_id')->nullable()->constrained('key_controls');
            $table->boolean('is_corrected')->default(false);
            $table->timestamps();

            $table->index(['room_id', 'returned_at', 'is_corrected']);
            $table->index('original_key_control_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_controls');
    }
};
