<?php

namespace Tests\Feature;

use App\Filament\Resources\ScheduleConflictResource;
use App\Filament\Resources\ScheduleResource;
use App\Filament\Resources\TeacherSubjectResource;
use App\Filament\Widgets\WeeklyScheduleWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_maestro_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/maestro');
    }

    public function test_maestro_login_loads_the_brand_assets(): void
    {
        $this->get('/maestro/login')
            ->assertOk()
            ->assertSee('maestro-logo-light.svg')
            ->assertSee('maestro-logo-dark.svg')
            ->assertSee('Conservatório – Escola das Artes da Madeira, Eng. Luiz Peter Clode')
            ->assertSee('Versão: V.1')
            ->assertSee('/css/maestro.css', escape: false);

        $this->assertFileExists(public_path('images/maestro-symbol.svg'));
    }

    public function test_public_institutional_pages_are_available_without_authentication(): void
    {
        foreach (['/privacidade', '/seguranca', '/suporte'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_user_without_a_role_cannot_access_maestro_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/maestro')
            ->assertForbidden();
    }

    public function test_application_resources_use_the_maestro_navigation_groups(): void
    {
        $groups = collect(Filament::getPanel('admin')->getResources())
            ->filter(fn (string $resource): bool => str_starts_with($resource, 'App\\Filament\\Resources\\'))
            ->map(fn (string $resource): ?string => $resource::getNavigationGroup())
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            'Académico',
            'Ajuda',
            'Horários',
            'Recursos',
            'Administração',
        ], $groups);
    }

    public function test_teacher_schedule_shortcut_requires_authentication(): void
    {
        $this->get('/meuhorario')->assertRedirect('/maestro/login');
    }

    public function test_teacher_schedule_shortcut_is_limited_to_professors(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Aluno'));

        $this->actingAs($user)->get('/meuhorario')->assertForbidden();
        $this->get('/maestro/o-meu-horario')->assertForbidden();
    }

    public function test_professor_can_open_their_schedule_page_from_the_shortcut(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Professor'));

        $this->actingAs($user)->get('/meuhorario')->assertRedirect('/maestro/o-meu-horario');
        $this->get('/maestro/o-meu-horario')
            ->assertOk()
            ->assertSee('O Meu Horário')
            ->assertSee('Professor')
            ->assertSee('id="calendar-container"', escape: false)
            ->assertSee('/css/maestro.css', escape: false);

        $this->assertNotContains(
            WeeklyScheduleWidget::class,
            Filament::getPanel('admin')->getWidgets(),
        );
    }

    public function test_professor_sees_their_subjects_below_their_schedule(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Professor'));

        $this->actingAs($user);

        $this->assertTrue(TeacherSubjectResource::canViewAny());
        $this->assertFalse(ScheduleResource::shouldRegisterNavigation());
        $this->assertFalse(ScheduleConflictResource::shouldRegisterNavigation());
        $this->assertSame('Horários', TeacherSubjectResource::getNavigationGroup());
        $this->assertSame('As minhas disciplinas', TeacherSubjectResource::getNavigationLabel());
        $this->assertSame(2, TeacherSubjectResource::getNavigationSort());
    }
}
