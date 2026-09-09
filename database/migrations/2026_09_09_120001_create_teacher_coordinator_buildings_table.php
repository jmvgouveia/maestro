<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_coordinator_buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_teacher')
                ->constrained('teachers')
                ->cascadeOnDelete();
            $table->foreignId('id_building')
                ->constrained('buildings')
                ->cascadeOnDelete();
            $table->foreignId('id_schoolyear')
                ->constrained('schoolyears')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['id_teacher', 'id_building', 'id_schoolyear'], 'teacher_building_schoolyear_unique');
            $table->index(['id_teacher', 'id_schoolyear'], 'teacher_coordinator_buildings_teacher_year_idx');
            $table->index(['id_building', 'id_schoolyear'], 'teacher_coordinator_buildings_building_year_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_coordinator_buildings');
    }
};
