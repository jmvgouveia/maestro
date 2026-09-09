<?php

namespace Tests\Feature;

use App\Filament\Resources\ScheduleResource\Traits\CheckScheduleWindow;
use App\Models\Classes;
use App\Models\Course;
use App\Models\SchoolYear;
use App\Models\User;
use Carbon\Carbon;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleWindowByCourseTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01');
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_specialized_window_allows_schedule_marking(): void
    {
        $class = $this->createClass('Especializado');
        $this->createSchoolYear();

        (new ScheduleWindowProbe)->check([$class->id]);

        $this->assertTrue(true);
    }

    public function test_closed_window_identifies_the_course_type(): void
    {
        $class = $this->createClass('Profissional');
        $this->createSchoolYear(['start_date_profissional' => '2026-11-01']);

        $this->expectException(Halt::class);
        $this->expectExceptionMessage('Profissional');

        (new ScheduleWindowProbe)->check([$class->id]);
    }

    public function test_mixed_types_require_every_window_to_be_open(): void
    {
        $specialized = $this->createClass('Especializado');
        $professional = $this->createClass('Profissional');
        $this->createSchoolYear(['start_date_profissional' => '2026-11-01']);

        $this->expectException(Halt::class);
        $this->expectExceptionMessage('Profissional');

        (new ScheduleWindowProbe)->check([$specialized->id, $professional->id]);
    }

    public function test_course_without_type_is_rejected(): void
    {
        $class = $this->createClass(null);
        $this->createSchoolYear();

        $this->expectException(Halt::class);
        $this->expectExceptionMessage('Configure a tipologia');

        (new ScheduleWindowProbe)->check([$class->id]);
    }

    public function test_super_admin_bypasses_closed_windows(): void
    {
        $class = $this->createClass('Livre');
        $this->createSchoolYear(['start_date_livre' => '2026-11-01']);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Super Admin'));
        $this->actingAs($admin);

        (new ScheduleWindowProbe)->check([$class->id]);

        $this->assertTrue(true);
    }

    private function createSchoolYear(array $overrides = []): SchoolYear
    {
        return SchoolYear::create(array_merge([
            'schoolyear' => '2026/2027',
            'start_date_especializado' => '2026-09-01',
            'end_date_especializado' => '2027-08-31',
            'start_date_profissional' => '2026-09-01',
            'end_date_profissional' => '2027-08-31',
            'start_date_livre' => '2026-09-01',
            'end_date_livre' => '2027-08-31',
            'active' => true,
        ], $overrides));
    }

    private function createClass(?string $type): Classes
    {
        $course = Course::create([
            'name' => 'Curso '.uniqid(),
            'type' => $type,
        ]);

        return Classes::create([
            'name' => 'Turma '.uniqid(),
            'id_course' => $course->id,
            'year' => 1,
        ]);
    }
}

class ScheduleWindowProbe
{
    use CheckScheduleWindow;

    public function check(array $classIds): void
    {
        $this->validateScheduleWindow($classIds);
    }
}
