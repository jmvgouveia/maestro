<?php

namespace Tests\Feature;

use App\Filament\Widgets\ScheduleWindowsWidget;
use App\Filament\Widgets\SchoolYearReadinessWidget;
use App\Models\Course;
use App\Models\SchoolYear;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleWindowsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_all_course_type_windows_and_their_states(): void
    {
        Carbon::setTestNow('2026-09-10');
        $this->createRole('Professor');
        $this->actingAs(User::factory()->create())->get('/');
        auth()->user()->assignRole('Professor');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        SchoolYear::create([
            'schoolyear' => '2026/2027',
            'active' => true,
            'start_date_especializado' => '2026-09-01',
            'end_date_especializado' => '2026-09-15',
            'start_date_profissional' => '2026-09-20',
            'end_date_profissional' => '2026-09-30',
            'start_date_livre' => null,
            'end_date_livre' => null,
        ]);

        $periods = app(ScheduleWindowsWidget::class)->periods();

        $this->assertCount(3, $periods);
        $this->assertSame('Aberto', $periods[0]['status']);
        $this->assertSame('Ainda não começou', $periods[1]['status']);
        $this->assertSame('Não configurado', $periods[2]['status']);
    }

    public function test_student_sees_only_the_student_registration_window(): void
    {
        $this->createRole('Aluno');
        $user = User::factory()->create();
        $user->assignRole('Aluno');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        SchoolYear::create([
            'schoolyear' => '2026/2027',
            'active' => true,
            'start_date_registration' => '2026-09-01',
            'end_date_registration' => '2026-09-30',
        ]);

        $periods = app(ScheduleWindowsWidget::class)->periods();

        $this->assertCount(1, $periods);
        $this->assertSame('Marcação dos alunos', $periods[0]['name']);
    }

    public function test_readiness_check_reports_missing_configuration(): void
    {
        Course::create(['name' => 'Curso sem tipologia']);
        SchoolYear::create(['schoolyear' => '2026/2027', 'active' => true]);

        $checks = app(SchoolYearReadinessWidget::class)->checks();

        $this->assertSame('Concluído', $checks[0]['status']);
        $this->assertSame('Bloqueante', $checks[1]['status']);
        $this->assertSame('Bloqueante', $checks[2]['status']);
        $this->assertSame('Bloqueante', $checks[3]['status']);
        $this->assertSame('Atenção', $checks[4]['status']);
        $this->assertSame('Atenção', $checks[5]['status']);
        $this->assertSame('Atenção', $checks[6]['status']);
        $this->assertSame('Atenção', $checks[7]['status']);
    }

    public function test_readiness_check_reports_no_active_school_year(): void
    {
        $checks = app(SchoolYearReadinessWidget::class)->checks();

        $this->assertCount(1, $checks);
        $this->assertSame('Bloqueante', $checks[0]['status']);
    }

    private function createRole(string $name): void
    {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
