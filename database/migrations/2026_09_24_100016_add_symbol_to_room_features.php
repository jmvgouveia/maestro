<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_features', function (Blueprint $table): void {
            $table->string('symbol', 4)->default('?')->after('name');
        });

        foreach (['Piano' => 'P', 'Cravo' => 'C', 'Órgão' => 'Ó', 'Harpa' => 'H', 'Sala de teoria' => 'T'] as $name => $symbol) {
            DB::table('room_features')->where('name', $name)->update(['symbol' => $symbol]);
        }
    }

    public function down(): void
    {
        Schema::table('room_features', fn (Blueprint $table) => $table->dropColumn('symbol'));
    }
};
