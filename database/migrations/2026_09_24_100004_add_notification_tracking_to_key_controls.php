<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_controls', function (Blueprint $table) {
            $table->timestamp('individual_notification_sent_at')->nullable()->after('release_reason');
            $table->date('included_in_summary_at')->nullable()->after('individual_notification_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('key_controls', function (Blueprint $table) {
            $table->dropColumn(['individual_notification_sent_at', 'included_in_summary_at']);
        });
    }
};
