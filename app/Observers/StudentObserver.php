<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\User;
use App\Services\UserActivationService;

class StudentObserver
{
    /**
     * Handle the Student "created" event.
     */
    public function created(Student $student): void
    {
        // Só cria se ainda não existir user_id
        if (! $student->user_id && filled($student->email)) {
            $user = User::firstOrCreate(['email' => $student->email], [
                'name' => $student->name,
                'password' => str()->random(40),
                'is_active' => false,
            ]);

            if ($user->wasRecentlyCreated) {
                $user->assignRole('Aluno');
                app(UserActivationService::class)->issueAndNotify($user);
            }

            $student->update(['user_id' => $user->id]);
        }
    }

    /**
     * Handle the Student "updated" event.
     */
    public function updated(Student $student): void
    {
        //
    }

    /**
     * Handle the Student "deleted" event.
     */
    public function deleted(Student $student): void
    {
        //
    }

    /**
     * Handle the Student "restored" event.
     */
    public function restored(Student $student): void
    {
        //
    }

    /**
     * Handle the Student "force deleted" event.
     */
    public function forceDeleted(Student $student): void
    {
        //
    }
}
