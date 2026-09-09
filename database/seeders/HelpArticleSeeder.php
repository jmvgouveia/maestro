<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'title' => 'Como começar',
                'slug' => 'como-comecar',
                'audience' => 'todos',
                'sort' => 10,
                'content' => "Consulte o dashboard para ver os períodos de marcação e os avisos importantes.\n\nUse o menu lateral para abrir o seu horário e as funcionalidades disponíveis para o seu perfil.\n\nMantenha os seus dados e a palavra-passe atualizados em A minha conta.",
            ],
            [
                'title' => 'Conta e segurança',
                'slug' => 'conta-e-seguranca',
                'audience' => 'todos',
                'sort' => 20,
                'content' => "Nunca partilhe a sua palavra-passe ou códigos de autenticação.\n\nA autenticação multifator pode ser configurada nas definições da conta.\n\nTermine a sessão quando utilizar um computador partilhado.",
            ],
            [
                'title' => 'Alunos',
                'slug' => 'alunos',
                'audience' => 'aluno',
                'sort' => 30,
                'content' => "Consulte no dashboard o período em que pode marcar as suas disciplinas.\n\nAbra o seu horário para consultar dias, horas, salas e edifícios.\n\nSe precisar de trocar um horário, utilize o pedido de troca e acompanhe o seu estado.",
            ],
            [
                'title' => 'Professores',
                'slug' => 'professores',
                'audience' => 'professor',
                'sort' => 30,
                'content' => "Consulte no dashboard as janelas de marcação por tipologia de curso.\n\nUse O meu horário para consultar as disciplinas e horas atribuídas.\n\nOs pedidos de troca devem ser acompanhados até ficarem aprovados ou recusados.",
            ],
            [
                'title' => 'Equipa administrativa',
                'slug' => 'equipa-administrativa',
                'audience' => 'administrativo',
                'sort' => 30,
                'content' => "Use o Estado de preparação do ano letivo para identificar bloqueios antes de abrir as marcações.\n\nConsulte os avisos de alunos sem matrícula, professores sem disciplinas e cursos sem tipologia.\n\nFaça as alterações através dos recursos correspondentes e confirme o resultado no dashboard.",
            ],
        ];

        foreach ($articles as $article) {
            HelpArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [...$article, 'is_published' => true],
            );
        }
    }
}
