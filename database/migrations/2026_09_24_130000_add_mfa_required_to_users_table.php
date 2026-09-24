<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('mfa_required')->default(false)->after('is_active');
        });

        DB::table('users')
            ->whereIn(
                'id',
                DB::table('two_factor_authentications')
                    ->where('authenticatable_type', User::class)
                    ->whereNotNull('enabled_at')
                    ->select('authenticatable_id')
            )
            ->update(['mfa_required' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('mfa_required');
        });
    }
};
