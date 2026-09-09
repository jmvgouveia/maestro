<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('type')->nullable()->after('name');
            $table->index('type');
        });

        Schema::table('schoolyears', function (Blueprint $table): void {
            $table->date('start_date_especializado')->nullable()->after('active');
            $table->date('end_date_especializado')->nullable()->after('start_date_especializado');
            $table->date('start_date_profissional')->nullable()->after('end_date_especializado');
            $table->date('end_date_profissional')->nullable()->after('start_date_profissional');
            $table->date('start_date_livre')->nullable()->after('end_date_profissional');
            $table->date('end_date_livre')->nullable()->after('start_date_livre');
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('schoolyears', function (Blueprint $table): void {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->dropColumn([
                'start_date_especializado',
                'end_date_especializado',
                'start_date_profissional',
                'end_date_profissional',
                'start_date_livre',
                'end_date_livre',
            ]);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
