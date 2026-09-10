<?php

namespace App\Providers;

use App\Listeners\RevokeSessionsAfterPasswordReset;
use App\Models\CourseSubject;
use App\Models\EmailAudit;
use App\Models\Registration;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Policies\EmailAuditPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Lab404\Impersonate\Services\ImpersonateManager;
use App\Policies\RolePolicy;
use App\Policies\PermissionPolicy;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Observers\StudentObserver;
use App\Models\Student;
use App\Models\Teacher;
use App\Observers\TeacherObserver;
use Filament\Notifications\Auth\ResetPassword as FilamentResetPassword;
use App\Notifications\ResetPasswordNotification;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FilamentResetPassword::class, ResetPasswordNotification::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->setLocale(session('locale', 'pt_PT')); // ou cookie, user preference, etc.

        // Registar cores adicionais a usar no projeto
        FilamentColor::register([
            'forest_green' => Color::hex('#228B22'),
            'blue_mh' => Color::hex('#0094ee'),
            'green_aprovado' => Color::hex('#065f46'),
            'blue_troca' => Color::hex('#2563eb'),
            'red_rejeitado' => Color::hex('#dc2626'),
            'purple_escalado' => Color::hex('#7c3aed'),
            'yellow_pendente' => Color::hex('#ca8a04'),
        ]);

        // Políticas de permissões de utilizadores e Super Admin
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(EmailAudit::class, EmailAuditPolicy::class);

        Gate::before(function (User $user, string $ability, array $arguments = []) {
            $record = $arguments[0] ?? null;
            $isSchoolYearRecord = $record instanceof Registration
                || $record instanceof TeacherSubject
                || $record instanceof CourseSubject;

            if (
                $isSchoolYearRecord
                && in_array($ability, ['update', 'delete', 'forceDelete', 'restore', 'replicate'], true)
                && (int) $record->id_schoolyear !== (int) \App\Models\SchoolYear::query()->where('active', true)->value('id')
            ) {
                return false;
            }

            if (
                app(ImpersonateManager::class)->isImpersonating()
                && in_array($ability, [
                    'create',
                    'update',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                    'restore',
                    'restoreAny',
                    'replicate',
                    'reorder',
                ], true)
            ) {
                return false;
            }

            if (
                $record instanceof User
                && $user->is($record)
                && in_array($ability, ['delete', 'forceDelete'], true)
            ) {
                return false;
            }

            return $user->isSuperAdmin() ? true : null;
        });

        Event::listen(PasswordReset::class, RevokeSessionsAfterPasswordReset::class);

        Student::observe(StudentObserver::class);
        //  Teacher::observe(TeacherObserver::class);
    }
}
