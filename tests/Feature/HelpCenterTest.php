<?php

namespace Tests\Feature;

use App\Filament\Pages\HelpCenter;
use App\Models\HelpArticle;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_gets_student_help_without_administrative_instructions(): void
    {
        $user = $this->userWithRole('Aluno');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        HelpArticle::create([
            'title' => 'Ajuda para alunos',
            'slug' => 'ajuda-alunos',
            'audience' => 'aluno',
            'content' => 'Conteúdo para alunos.',
        ]);
        HelpArticle::create([
            'title' => 'Ajuda administrativa',
            'slug' => 'ajuda-administrativa',
            'audience' => 'administrativo',
            'content' => 'Conteúdo administrativo.',
        ]);

        $titles = app(HelpCenter::class)->getArticles()->pluck('title')->all();

        $this->assertContains('Ajuda para alunos', $titles);
        $this->assertNotContains('Ajuda administrativa', $titles);
    }

    public function test_pedagogical_area_gets_administrative_help(): void
    {
        $user = $this->userWithRole('Área Pedagógica');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        HelpArticle::create([
            'title' => 'Ajuda administrativa',
            'slug' => 'ajuda-administrativa',
            'audience' => 'administrativo',
            'content' => 'Conteúdo administrativo.',
        ]);

        $titles = app(HelpCenter::class)->getArticles()->pluck('title')->all();

        $this->assertTrue(HelpCenter::canAccess());
        $this->assertContains('Ajuda administrativa', $titles);
    }

    private function userWithRole(string $role): User
    {
        $role = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
