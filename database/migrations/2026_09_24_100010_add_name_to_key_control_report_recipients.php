<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_control_report_recipients', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('key_control_report_recipients', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
