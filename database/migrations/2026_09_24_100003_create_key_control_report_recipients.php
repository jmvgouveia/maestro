<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_control_report_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('report_types');
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['report_types'], 'key_control_report_recipients_types_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_control_report_recipients');
    }
};
