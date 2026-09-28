<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Classes;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\Room;
use App\Models\RoomFeature;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserBuildingAuthorization;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create('pt_PT');

        $this->call(RolesAndPermissionsSeeder::class);

        $schoolYear = SchoolYear::query()->firstOrCreate(
            ['schoolyear' => '2026/2027'],
            [
                'start_date_especializado' => '2026-09-01',
                'end_date_especializado' => '2027-08-31',
                'active' => ! SchoolYear::query()->where('active', true)->exists(),
            ],
        );

        $buildings = collect([
            ['name' => 'Edifício de Teste A', 'address' => 'Rua de Teste A, Funchal'],
            ['name' => 'Edifício de Teste B', 'address' => 'Rua de Teste B, Funchal'],
        ])->map(fn (array $data): Building => Building::query()->firstOrCreate(
            ['name' => $data['name']],
            ['address' => $data['address']],
        ));

        $features = collect([
            ['name' => 'Piano', 'symbol' => 'P'],
            ['name' => 'Computadores', 'symbol' => 'PC'],
            ['name' => 'Projetor', 'symbol' => 'PR'],
        ])->map(fn (array $data): RoomFeature => RoomFeature::query()->firstOrCreate(
            ['name' => $data['name']],
            ['symbol' => $data['symbol']],
        ));

        foreach ($buildings as $buildingIndex => $building) {
            foreach (range(1, 6) as $roomIndex) {
                $room = Room::query()->firstOrCreate(
                    ['name' => sprintf('Sala T%d.%02d', $buildingIndex + 1, $roomIndex), 'id_building' => $building->id],
                    [
                        'description' => $faker->sentence(6),
                        'show_on_occupancy_map' => true,
                    ],
                );

                $room->features()->syncWithoutDetaching([
                    $features[($roomIndex - 1) % $features->count()]->id,
                ]);
            }
        }

        $user = User::query()->where('email', 'j.gouveia@edu.madeira.gov.pt')->first()
            ?? User::query()->where('email', 'admin@admin.pt')->firstOrFail();

        foreach ($buildings as $building) {
            UserBuildingAuthorization::query()->firstOrCreate([
                'user_id' => $user->id,
                'building_id' => $building->id,
            ], [
                'created_by' => $user->id,
            ]);
        }

        $courses = collect([
            ['name' => 'Curso de Teste Instrumento', 'type' => 'Especializado'],
            ['name' => 'Curso de Teste Formação Musical', 'type' => 'Especializado'],
        ])->map(fn (array $data): Course => Course::query()->firstOrCreate(
            ['name' => $data['name']],
            ['type' => $data['type']],
        ));

        $subjects = collect([
            ['name' => 'Disciplina de Teste Piano', 'acronym' => 'TST-PNO'],
            ['name' => 'Disciplina de Teste Teoria', 'acronym' => 'TST-TEO'],
            ['name' => 'Disciplina de Teste Classe', 'acronym' => 'TST-CLA'],
        ])->map(fn (array $data): Subject => Subject::query()->firstOrCreate(
            ['acronym' => $data['acronym']],
            [
                'name' => $data['name'],
                'type' => 'Letiva',
                'status' => true,
                'student_can_enroll' => true,
            ],
        ));

        foreach ($courses as $course) {
            foreach ($subjects as $subject) {
                CourseSubject::query()->firstOrCreate([
                    'id_course' => $course->id,
                    'id_subject' => $subject->id,
                    'id_schoolyear' => $schoolYear->id,
                ]);
            }
        }

        foreach ($courses as $courseIndex => $course) {
            foreach (range(1, 2) as $classIndex) {
                $class = Classes::query()->firstOrCreate([
                    'name' => sprintf('Turma Teste %d.%d', $courseIndex + 1, $classIndex),
                    'id_course' => $course->id,
                ], [
                    'year' => $classIndex,
                ]);

                $class->buildings()->syncWithoutDetaching([
                    $buildings[($classIndex - 1) % $buildings->count()]->id,
                ]);
            }
        }

        if (DB::table('timeperiods')->count() === 0) {
            foreach (range(8, 20) as $hour) {
                DB::table('timeperiods')->insert([
                    'description' => sprintf('%02d:00-%02d:00', $hour, $hour + 1),
                    'start_time' => sprintf('%02d:00:00', $hour),
                    'end_time' => sprintf('%02d:00:00', $hour + 1),
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->call(DemoDataSeeder::class);
    }
}
