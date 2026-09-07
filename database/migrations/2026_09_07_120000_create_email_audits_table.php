<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->unique();
            $table->string('recipient_email');
            $table->string('subject');
            $table->string('notification_type')->nullable();
            $table->string('mailer');
            $table->string('status');
            $table->timestamp('attempted_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'attempted_at']);
            $table->index(['recipient_email', 'attempted_at']);
            $table->index('notification_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_audits');
    }
};
