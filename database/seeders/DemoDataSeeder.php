<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Registration;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create('pt_PT');

        DB::transaction(function () use ($faker): void {
            $schoolYear = SchoolYear::query()->where('active', true)->first()
                ?? SchoolYear::query()->latest('id')->first();

            if (! $schoolYear) {
                throw new \RuntimeException('É necessário ter pelo menos um ano letivo criado antes de executar o seeder demo.');
            }

            $subjects = Subject::query()
                ->where('status', true)
                ->where('acronym', 'not like', 'DEMO-%')
                ->get();
            $courses = Course::query()
                ->where('name', 'not like', '[DEMO]%')
                ->get();
            $classes = Classes::query()->whereIn('id_course', $courses->modelKeys())->get();

            if ($subjects->isEmpty() || $courses->isEmpty() || $classes->isEmpty()) {
                throw new \RuntimeException('É necessário ter disciplinas, cursos e turmas criados antes de executar o seeder demo.');
            }

            $teachers = collect(range(1, 12))->map(function (int $index) use ($faker): Teacher {
                return Teacher::query()->updateOrCreate(
                    ['acronym' => 'D' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
                    [
                        'number' => 9000 + $index,
                        'name' => $faker->name(),
                        'birthdate' => $faker->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
                        'startingdate' => $faker->dateTimeBetween('-20 years', '-1 year')->format('Y-m-d'),
                    ]
                );
            });

            foreach ($teachers as $index => $teacher) {
                $subject = $subjects->values()->get($index % $subjects->count());
                DB::table('teacher_subjects')->updateOrInsert(
                    ['id_teacher' => $teacher->id, 'id_subject' => $subject->id, 'id_schoolyear' => $schoolYear->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            $students = collect(range(1, 30))->map(function (int $index) use ($faker): Student {
                return Student::query()->updateOrCreate(
                    ['number' => 'DEMO' . str_pad((string) $index, 3, '0', STR_PAD_LEFT)],
                    [
                        'name' => $faker->name(),
                        'email' => 'aluno.demo.' . $index . '@example.test',
                        'birthdate' => $faker->dateTimeBetween('-22 years', '-16 years')->format('Y-m-d'),
                    ]
                );
            });

            foreach ($students as $index => $student) {
                $class = $classes->values()->get($index % $classes->count());
                $course = $courses->firstWhere('id', $class->id_course);
                $registration = Registration::query()->updateOrCreate(
                    ['id_student' => $student->id, 'id_schoolyear' => $schoolYear->id],
                    ['id_course' => $course->id, 'id_class' => $class->id]
                );

                $courseSubjectIds = DB::table('course_subjects')
                    ->where('id_course', $course->id)
                    ->where('id_schoolyear', $schoolYear->id)
                    ->pluck('id_subject');

                $registration->subjects()->sync($courseSubjectIds->mapWithKeys(fn (int $subjectId): array => [
                    $subjectId => ['shift' => $index % 2 === 0 ? 'Manhã' : 'Tarde'],
                ])->all());
            }
        });
    }
}
