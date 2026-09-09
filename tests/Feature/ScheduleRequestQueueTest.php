<?php

namespace Tests\Feature;

use App\Helpers\ScheduleRequestQueueHelper;
use App\Models\Schedule;
use App\Models\ScheduleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleRequestQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_keeps_owner_and_requester_separately(): void
    {
        $fixture = $this->createFixture();

        $request = ScheduleRequest::create([
            'id_schedule' => $fixture['schedule']->id,
            'id_teacher' => $fixture['owner']->id,
            'id_teacher_requester' => $fixture['requester']->id,
            'id_schoolyear' => $fixture['schoolYear']->id,
            'id_new_schedule' => $fixture['newSchedule']->id,
            'justification' => 'Troca necessária.',
            'status' => 'Pendente',
        ]);

        $this->assertDatabaseHas('schedule_requests', [
            'id' => $request->id,
            'id_teacher' => $fixture['owner']->id,
            'id_teacher_requester' => $fixture['requester']->id,
            'id_schoolyear' => $fixture['schoolYear']->id,
            'status' => 'Pendente',
        ]);
        $this->assertSame($fixture['requester']->id, $request->requester->id);
        $this->assertSame($fixture['schoolYear']->id, $request->schoolyear->id);
    }

    public function test_pending_requests_are_ordered_by_creation_time(): void
    {
        $fixture = $this->createFixture();
        $oldest = now()->subMinute();

        $later = ScheduleRequest::create($this->requestData($fixture));
        $later->created_at = now();
        $later->save();

        $first = ScheduleRequest::create($this->requestData($fixture));
        $first->created_at = $oldest;
        $first->save();

        $next = ScheduleRequest::where('id_schedule', $fixture['schedule']->id)
            ->where('status', 'Pendente')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        $this->assertSame($first->id, $next->id);
        $this->assertNotSame('Aguardando', $first->status);
        $this->assertNotSame('Aguardando', $later->status);
    }

    public function test_only_the_oldest_pending_request_is_first_in_queue(): void
    {
        $fixture = $this->createFixture();
        $oldest = ScheduleRequest::create($this->requestData($fixture));
        $newest = ScheduleRequest::create($this->requestData($fixture));

        $oldest->update(['created_at' => now()->subMinute()]);

        $this->assertTrue(ScheduleRequestQueueHelper::isFirstPending($fixture['schedule']->id, $oldest->id));
        $this->assertFalse(ScheduleRequestQueueHelper::isFirstPending($fixture['schedule']->id, $newest->id));
    }

    public function test_administrative_deletion_has_a_distinct_status(): void
    {
        $fixture = $this->createFixture();
        $request = ScheduleRequest::create([
            ...$this->requestData($fixture),
            'status' => 'Eliminado DP',
        ]);

        $this->assertDatabaseHas('schedule_requests', [
            'id' => $request->id,
            'status' => 'Eliminado DP',
        ]);
    }

    private function requestData(array $fixture): array
    {
        return [
            'id_schedule' => $fixture['schedule']->id,
            'id_teacher' => $fixture['owner']->id,
            'id_teacher_requester' => $fixture['requester']->id,
            'id_schoolyear' => $fixture['schoolYear']->id,
            'id_new_schedule' => $fixture['newSchedule']->id,
            'justification' => 'Troca necessária.',
            'status' => 'Pendente',
        ];
    }

    private function createFixture(): array
    {
        $now = now();
        $schoolYearId = DB::table('schoolyears')->insertGetId([
            'schoolyear' => '2026/2027',
            'start_date_especializado' => '2026-09-01',
            'end_date_especializado' => '2027-07-31',
            'start_date_profissional' => '2026-09-01',
            'end_date_profissional' => '2027-07-31',
            'start_date_livre' => '2026-09-01',
            'end_date_livre' => '2027-07-31',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ownerId = $this->createTeacher('Owner', 'OWN', 1001);
        $requesterId = $this->createTeacher('Requester', 'REQ', 1002);
        $buildingId = DB::table('buildings')->insertGetId([
            'name' => 'Building 1',
            'address' => 'Address 1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $timeperiodId = DB::table('timeperiods')->insertGetId([
            'description' => '09:00 - 10:00',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $roomId = DB::table('rooms')->insertGetId([
            'name' => 'Room 1',
            'id_building' => $buildingId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Subject 1',
            'acronym' => 'SUB1',
            'type' => 'Letiva',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scheduleData = [
            'id_schoolyear' => $schoolYearId,
            'id_timeperiod' => $timeperiodId,
            'id_room' => $roomId,
            'id_teacher' => $ownerId,
            'id_weekday' => 1,
            'id_subject' => $subjectId,
            'status' => 'Aprovado',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $scheduleId = DB::table('schedules')->insertGetId($scheduleData);
        $newScheduleId = DB::table('schedules')->insertGetId([
            ...$scheduleData,
            'id_teacher' => $requesterId,
            'id_room' => $roomId,
            'status' => 'Pendente',
        ]);

        return [
            'owner' => DB::table('teachers')->find($ownerId),
            'requester' => DB::table('teachers')->find($requesterId),
            'schoolYear' => DB::table('schoolyears')->find($schoolYearId),
            'schedule' => Schedule::findOrFail($scheduleId),
            'newSchedule' => Schedule::findOrFail($newScheduleId),
        ];
    }

    private function createTeacher(string $name, string $acronym, int $number): int
    {
        return DB::table('teachers')->insertGetId([
            'number' => $number,
            'name' => $name,
            'acronym' => $acronym,
            'birthdate' => '1980-01-01',
            'startingdate' => '2020-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
