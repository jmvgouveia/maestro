<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_control_floor_key_accesses', function (Blueprint $table): void {
            $table->string('occupant_type')->nullable()->after('accessed_by');
            $table->unsignedBigInteger('occupant_id')->nullable()->after('occupant_type');
            $table->timestamp('ended_at')->nullable()->after('occupant_id');
            $table->foreignId('ended_by')->nullable()->after('ended_at')->constrained('users');
            $table->index(['occupant_type', 'occupant_id']);
            $table->index(['room_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::table('key_control_floor_key_accesses', function (Blueprint $table): void {
            $table->dropIndex(['occupant_type', 'occupant_id']);
            $table->dropIndex(['room_id', 'ended_at']);
            $table->dropForeign(['ended_by']);
            $table->dropColumn(['occupant_type', 'occupant_id', 'ended_at', 'ended_by']);
        });
    }
};
