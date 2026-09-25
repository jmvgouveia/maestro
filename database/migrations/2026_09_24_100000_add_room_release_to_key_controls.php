<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_controls', function (Blueprint $table) {
            if (! Schema::hasColumn('key_controls', 'room_released_at')) {
                $table->timestamp('room_released_at')->nullable()->after('returned_at');
            }
            if (! Schema::hasColumn('key_controls', 'room_released_by')) {
                $table->foreignId('room_released_by')->nullable()->after('room_released_at')->constrained('users');
            }
            if (! Schema::hasColumn('key_controls', 'release_type')) {
                $table->string('release_type', 40)->nullable()->after('room_released_by');
            }
            if (! Schema::hasColumn('key_controls', 'release_reason')) {
                $table->text('release_reason')->nullable()->after('release_type');
            }
        });

        $hasIndex = collect(Schema::getIndexes('key_controls'))
            ->contains(fn (array $index): bool => $index['name'] === 'key_controls_room_release_status_index');

        if (! $hasIndex) {
            Schema::table('key_controls', function (Blueprint $table): void {
                $table->index(
                    ['room_id', 'room_released_at', 'returned_at', 'is_corrected'],
                    'key_controls_room_release_status_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('key_controls', function (Blueprint $table) {
            $table->dropIndex('key_controls_room_release_status_index');
            $table->dropForeign(['room_released_by']);
            $table->dropColumn(['room_released_at', 'room_released_by', 'release_type', 'release_reason']);
        });
    }
};
