<?php

namespace Tests\Feature;

use App\Filament\Pages\AccessAudit;
use App\Filament\Pages\StudentsWithoutSchedule;
use App\Filament\Pages\TeacherSubjectShiftAudit;
use App\Filament\Resources\ScheduleResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_auditoria_role_can_access_audit_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(AccessAudit::canAccess());
        $this->assertFalse(StudentsWithoutSchedule::canAccess());
        $this->assertFalse(TeacherSubjectShiftAudit::canAccess());

        $user->assignRole(Role::findByName('Auditoria'));

        $this->assertTrue(AccessAudit::canAccess());
        $this->assertTrue(StudentsWithoutSchedule::canAccess());
        $this->assertTrue(TeacherSubjectShiftAudit::canAccess());
    }

    public function test_audit_can_filter_users_who_never_logged_in(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('Auditoria'));
        $neverLoggedIn = User::factory()->create(['name' => 'Nunca Entrou']);
        $loggedIn = User::factory()->create([
            'name' => 'Ja Entrou',
            'last_login_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(AccessAudit::class)
            ->set('onlyNeverLoggedIn', true)
            ->assertSee('Nunca Entrou')
            ->assertDontSee('Ja Entrou');
    }

    public function test_gestor_de_horarios_can_access_schedule_resource_but_not_audits(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Gestor de Horários'));
        $this->actingAs($user);

        $this->assertTrue($user->can('view Schedule'));
        $this->assertTrue($user->can('update Schedule'));
        $this->assertFalse($user->can('create Schedule'));
        $this->assertFalse($user->can('delete Schedule'));
        $this->assertTrue(ScheduleResource::canViewAny());
        $this->assertFalse(AccessAudit::canAccess());
        $this->assertFalse(TeacherSubjectShiftAudit::canAccess());
    }
}
