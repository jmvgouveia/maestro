<?php

namespace Tests\Feature;

use App\Filament\Pages\FormacaoMusicalCoordination;
use App\Filament\Pages\IniciacaoMusicalCoordination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MusicalCoordinationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_musical_role_only_sees_its_own_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Coordenador de Formação Musical'));

        $this->actingAs($user);

        $this->assertTrue(FormacaoMusicalCoordination::canAccess());
        $this->assertFalse(IniciacaoMusicalCoordination::canAccess());
    }

    public function test_user_with_both_musical_roles_sees_both_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole([
            Role::findByName('Coordenador de Formação Musical'),
            Role::findByName('Coordenador de Iniciação Musical'),
        ]);

        $this->actingAs($user);

        $this->assertTrue(FormacaoMusicalCoordination::canAccess());
        $this->assertTrue(IniciacaoMusicalCoordination::canAccess());
    }

    public function test_unrelated_user_cannot_access_musical_coordination(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse(FormacaoMusicalCoordination::canAccess());
        $this->assertFalse(IniciacaoMusicalCoordination::canAccess());
    }
}
