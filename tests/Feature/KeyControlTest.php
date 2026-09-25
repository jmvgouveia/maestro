<?php

namespace Tests\Feature;

use App\Filament\Pages\KeyControlOperation;
use App\Filament\Resources\KeyControlResource;
use App\Filament\Resources\KeyControlResource\Pages\ListKeyControls;
use App\Filament\Resources\UserBuildingAuthorizationResource;
use App\Models\Building;
use App\Models\KeyControl;
use App\Models\KeyControlReportRecipient;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserBuildingAuthorization;
use App\Notifications\KeyControlDailySummaryNotification;
use App\Notifications\KeyControlReleasedNotification;
use App\Services\KeyControlClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KeyControlTest extends TestCase
{
    use RefreshDatabase;

    private function porter(): User
    {
        $role = Role::findOrCreate('Porteiro', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function keyManager(): User
    {
        $role = Role::findOrCreate('Gestão de Chaves', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function teacherUser(): User
    {
        $role = Role::findByName('Professor');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function room(): Room
    {
        $building = Building::create([
            'name' => 'Edifício A',
            'address' => 'Rua X',
        ]);

        return Room::create([
            'name' => 'Sala 1',
            'description' => 'Sala de teste',
            'id_building' => $building->id,
        ]);
    }

    private function teacher(): Teacher
    {
        return Teacher::create([
            'number' => 'T001',
            'name' => 'Professor Teste',
            'acronym' => 'PT',
            'birthdate' => '1980-01-01',
            'startingdate' => '2020-01-01',
        ]);
    }

    private function student(): Student
    {
        return Student::create([
            'number' => 'A1001',
            'name' => 'Aluno Teste',
            'birthdate' => '2010-01-01',
        ]);
    }

    public function test_porter_can_access_operation_page_and_key_manager_can_access_management_resources(): void
    {
        $porter = $this->porter();
        $manager = $this->keyManager();
        $teacher = $this->teacherUser();

        $this->actingAs($porter);
        $this->assertTrue($porter->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertTrue(KeyControlOperation::canAccess());
        $this->assertTrue(KeyControlResource::canViewAny());
        $this->assertFalse($porter->can('export key control'));
        $this->assertFalse(UserBuildingAuthorizationResource::canViewAny());

        $this->actingAs($manager);
        $this->assertTrue($manager->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertFalse(KeyControlOperation::canAccess());
        $this->assertTrue(KeyControlResource::canViewAny());
        $this->assertTrue($manager->can('export key control'));
        $this->assertTrue(UserBuildingAuthorizationResource::canViewAny());

        $this->actingAs($teacher);
        $this->assertTrue($teacher->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertFalse(KeyControlOperation::canAccess());
        $this->assertFalse(KeyControlResource::canViewAny());
    }

    public function test_key_manager_can_render_key_register_with_filters(): void
    {
        $manager = $this->keyManager();

        $this->actingAs($manager);

        Livewire::test(ListKeyControls::class)
            ->assertSuccessful();
    }

    public function test_porter_only_sees_authorized_rooms(): void
    {
        $porter = $this->porter();
        $room = $this->room();
        $otherBuilding = Building::create([
            'name' => 'Edifício B',
            'address' => 'Rua Y',
        ]);
        $otherRoom = Room::create([
            'name' => 'Sala 2',
            'description' => 'Outra sala',
            'id_building' => $otherBuilding->id,
        ]);

        UserBuildingAuthorization::create([
            'user_id' => $porter->id,
            'building_id' => $room->id_building,
            'created_by' => $porter->id,
        ]);

        $this->actingAs($porter);

        Livewire::test(KeyControlOperation::class)
            ->assertSet('rooms', function ($rooms) use ($room, $otherRoom) {
                $ids = $rooms->pluck('id')->all();

                return in_array($room->id, $ids, true) && ! in_array($otherRoom->id, $ids, true);
            });
    }

    public function test_room_summary_follows_selected_building(): void
    {
        $porter = $this->porter();
        $manager = $this->keyManager();
        $firstRoom = $this->room();
        $secondBuilding = Building::create([
            'name' => 'Edifício B',
            'address' => 'Rua Y',
        ]);
        $secondRoom = Room::create([
            'name' => 'Sala 2',
            'description' => 'Outra sala',
            'id_building' => $secondBuilding->id,
        ]);
        $teacher = $this->teacher();

        UserBuildingAuthorization::insert([
            [
                'user_id' => $porter->id,
                'building_id' => $firstRoom->id_building,
                'created_by' => $manager->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $porter->id,
                'building_id' => $secondBuilding->id,
                'created_by' => $manager->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        KeyControl::create([
            'room_id' => $firstRoom->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        $this->actingAs($porter);

        Livewire::test(KeyControlOperation::class)
            ->set('selectedBuildingId', $firstRoom->id_building)
            ->assertSet('authorizedRoomsCount', 1)
            ->assertSet('occupiedRoomsCount', 1)
            ->assertSet('availableRoomsCount', 0)
            ->set('selectedBuildingId', $secondBuilding->id)
            ->assertSet('authorizedRoomsCount', 1)
            ->assertSet('occupiedRoomsCount', 0)
            ->assertSet('availableRoomsCount', 1);
    }

    public function test_room_search_matches_active_holder_name_and_number(): void
    {
        $porter = $this->porter();
        $room = $this->room();
        $teacher = $this->teacher();

        UserBuildingAuthorization::create([
            'user_id' => $porter->id,
            'building_id' => $room->id_building,
            'created_by' => $porter->id,
        ]);

        KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        $this->actingAs($porter);

        Livewire::test(KeyControlOperation::class)
            ->set('search', 'Professor Teste')
            ->assertSet('rooms', fn ($rooms): bool => $rooms->contains('id', $room->id))
            ->set('search', 'T001')
            ->assertSet('rooms', fn ($rooms): bool => $rooms->contains('id', $room->id));
    }

    public function test_porter_can_pick_up_and_return_key(): void
    {
        $porter = $this->porter();
        $room = $this->room();
        $teacher = $this->teacher();

        UserBuildingAuthorization::create([
            'user_id' => $porter->id,
            'building_id' => $room->id_building,
            'created_by' => $porter->id,
        ]);

        $this->actingAs($porter);

        $this->assertNull($room->activeKeyControl);

        Livewire::test(KeyControlOperation::class)
            ->call('selectPickUp', $room->id)
            ->set('pickUpData.holder', Teacher::class.':'.$teacher->id)
            ->set('pickUpData.observations', 'Obs teste')
            ->call('submitPickUp')
            ->assertHasNoErrors();

        $active = KeyControl::query()->where('room_id', $room->id)->active()->first();
        $this->assertNotNull($active);
        $this->assertSame($teacher->id, $active->holder_id);
        $this->assertSame(Teacher::class, $active->holder_type);
        $this->assertSame('Obs teste', $active->pick_up_observations);

        Livewire::test(KeyControlOperation::class)
            ->call('selectReturn', $room->id)
            ->set('returnData.room_id', $room->id)
            ->set('returnData.observations', 'Devolvida')
            ->call('submitReturn')
            ->assertHasNoErrors();

        $active->refresh();
        $this->assertNotNull($active->returned_at);
        $this->assertSame('Devolvida', $active->return_observations);
    }

    public function test_porter_can_release_a_room_without_marking_the_key_as_returned(): void
    {
        $porter = $this->porter();
        $room = $this->room();
        $teacher = $this->teacher();

        UserBuildingAuthorization::create([
            'user_id' => $porter->id,
            'building_id' => $room->id_building,
            'created_by' => $porter->id,
        ]);

        KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        $this->actingAs($porter);

        Livewire::test(KeyControlOperation::class)
            ->call('releaseRoom', $room->id)
            ->assertHasNoErrors();

        $key = KeyControl::query()->first();
        $this->assertNull($key->returned_at);
        $this->assertNotNull($key->room_released_at);
        $this->assertTrue($room->fresh()->activeKeyControl === null);
        $this->assertDatabaseHas('key_control_events', [
            'key_control_id' => $key->id,
            'event_type' => 'room_released',
            'performed_by' => $porter->id,
        ]);

        Livewire::test(KeyControlOperation::class)
            ->call('openWithFloorKey', $room->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('key_control_floor_key_accesses', [
            'key_control_id' => $key->id,
            'room_id' => $room->id,
            'accessed_by' => $porter->id,
        ]);
        $this->assertDatabaseHas('key_control_events', [
            'key_control_id' => $key->id,
            'event_type' => 'floor_key_opened',
            'performed_by' => $porter->id,
        ]);
    }

    public function test_releasing_a_room_notifies_the_teacher_once(): void
    {
        Notification::fake();
        $porter = $this->porter();
        $teacherUser = $this->teacherUser();
        $teacher = $this->teacher();
        $teacher->update(['id_user' => $teacherUser->id]);
        $room = $this->room();
        $key = KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        app(KeyControlClosureService::class)->releaseRoom($key, $porter);

        Notification::assertSentTo($teacherUser, KeyControlReleasedNotification::class);
    }

    public function test_daily_closure_sends_summary_to_external_recipient(): void
    {
        Notification::fake();
        $porter = $this->porter();
        $room = $this->room();
        $teacher = $this->teacher();
        $key = KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);
        KeyControlReportRecipient::create([
            'type' => 'external',
            'email' => 'relatorio@example.test',
            'is_active' => true,
            'report_types' => [KeyControlClosureService::DAILY_REPORT_TYPE],
        ]);

        app(KeyControlClosureService::class)->dailyClosure();

        Notification::assertSentOnDemand(KeyControlDailySummaryNotification::class);
        $this->assertNotNull($key->fresh()->room_released_at);
        $this->assertNotNull($key->fresh()->included_in_summary_at);
    }

    public function test_duplicate_active_pickup_is_blocked(): void
    {
        $porter = $this->porter();
        $manager = $this->keyManager();
        $room = $this->room();
        $teacher = $this->teacher();
        $student = $this->student();

        UserBuildingAuthorization::create([
            'user_id' => $porter->id,
            'building_id' => $room->id_building,
            'created_by' => $manager->id,
        ]);

        KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        $this->actingAs($porter);

        Livewire::test(KeyControlOperation::class)
            ->call('selectPickUp', $room->id)
            ->set('pickUpData.holder', Student::class.':'.$student->id)
            ->call('submitPickUp')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('key_controls', 1);
        $this->assertSame($teacher->id, KeyControl::first()->holder_id);
    }

    public function test_same_person_cannot_hold_two_active_keys(): void
    {
        $porter = $this->porter();
        $room = $this->room();
        $secondRoom = Room::create([
            'name' => 'Sala 2',
            'description' => 'Outra sala',
            'id_building' => $room->id_building,
        ]);
        $teacher = $this->teacher();

        UserBuildingAuthorization::create([
            'user_id' => $porter->id,
            'building_id' => $room->id_building,
            'created_by' => $porter->id,
        ]);

        $this->actingAs($porter);

        Livewire::test(KeyControlOperation::class)
            ->call('selectPickUp', $room->id)
            ->set('pickUpData.holder', Teacher::class.':'.$teacher->id)
            ->call('submitPickUp');

        Livewire::test(KeyControlOperation::class)
            ->call('selectPickUp', $secondRoom->id)
            ->set('pickUpData.holder', Teacher::class.':'.$teacher->id)
            ->call('submitPickUp');

        $this->assertDatabaseCount('key_controls', 1);
    }

    public function test_porter_can_correct_own_latest_operation_but_not_older_or_others(): void
    {
        $porter = $this->porter();
        $otherPorter = $this->porter();
        $manager = $this->keyManager();
        $room = $this->room();
        $secondRoom = Room::create([
            'name' => 'Sala 2',
            'description' => 'Sala de correção',
            'id_building' => $room->id_building,
        ]);
        $teacher = $this->teacher();

        UserBuildingAuthorization::create([
            'user_id' => $porter->id,
            'building_id' => $room->id_building,
            'created_by' => $manager->id,
        ]);

        $record = KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        $this->actingAs($otherPorter);
        $this->assertFalse($otherPorter->can('update', $record));

        $this->actingAs($porter);
        $this->assertTrue($porter->can('update', $record));

        Livewire::test(KeyControlOperation::class)
            ->call('selectCorrect', $room->id)
            ->set('correctData.room_id', $secondRoom->id)
            ->set('correctData.holder', Teacher::class.':'.$teacher->id)
            ->set('correctData.reason', 'Engano no levantador')
            ->call('submitCorrect')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertTrue($record->is_corrected);

        $correction = KeyControl::query()->where('original_key_control_id', $record->id)->first();
        $this->assertNotNull($correction);
        $this->assertSame($secondRoom->id, $correction->room_id);
        $this->assertSame('Engano no levantador', $correction->correction_reason);
        $this->assertSame($porter->id, $correction->corrected_by);
        $this->assertFalse($porter->can('update', $correction));

        $this->actingAs($manager);
        $this->assertFalse($manager->can('update', $record));
        $this->assertFalse($manager->can('update', $correction));
    }

    public function test_key_manager_cannot_correct_operations(): void
    {
        $manager = $this->keyManager();
        $porter = $this->porter();
        $room = $this->room();
        $teacher = $this->teacher();

        $record = KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        $this->actingAs($manager);
        $this->assertFalse($manager->can('update', $record));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Só o operador que registou o movimento o pode corrigir.');
        KeyControlResource::correctRecord($record, 'Correção pela gestão');

        $returnedRecord = KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now()->subHour(),
            'returned_at' => now(),
            'picked_up_by' => $porter->id,
            'returned_by' => $porter->id,
        ]);

        $this->assertFalse($manager->can('update', $returnedRecord));
    }

    public function test_key_control_records_cannot_be_deleted(): void
    {
        $manager = $this->keyManager();
        $porter = $this->porter();
        $room = $this->room();
        $teacher = $this->teacher();

        $record = KeyControl::create([
            'room_id' => $room->id,
            'holder_type' => Teacher::class,
            'holder_id' => $teacher->id,
            'picked_up_at' => now(),
            'picked_up_by' => $porter->id,
        ]);

        $this->actingAs($manager);
        $this->assertFalse($manager->can('delete', $record));
        $this->assertFalse($manager->can('deleteAny', KeyControl::class));
    }
}
