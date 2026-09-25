<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_control_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_control_id')->constrained('key_controls');
            $table->foreignId('floor_key_access_id')->nullable()->constrained('key_control_floor_key_accesses');
            $table->string('event_type', 40);
            $table->timestamp('occurred_at');
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['key_control_id', 'occurred_at']);
            $table->index(['floor_key_access_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_control_events');
    }
};
