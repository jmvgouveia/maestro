<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_features', function (Blueprint $table): void {
            $table->string('icon_path')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('room_features', fn (Blueprint $table) => $table->dropColumn('icon_path'));
    }
};
