<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')
            ->whereNotNull('user_id')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->each(function (object $student): void {
                DB::table('student_guardians')->insertOrIgnore([
                    'user_id' => $student->user_id,
                    'student_id' => $student->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        $roleId = DB::table('roles')
            ->where('name', 'Encarregado de Educação')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            return;
        }

        DB::table('student_guardians')
            ->distinct()
            ->pluck('user_id')
            ->each(function (int $userId) use ($roleId): void {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('student_guardians')
            ->join('students', 'students.id', '=', 'student_guardians.student_id')
            ->whereColumn('students.user_id', 'student_guardians.user_id')
            ->delete();
    }
};
