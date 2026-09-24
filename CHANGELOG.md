# Changelog

## 2026-09-24

### Utilizadores e autenticacao

- Adicionada ativacao de conta com data de ativacao e gestao do estado da conta.
- Organizada a gestao de utilizadores por dados pessoais, conta, MFA, ativacao,
  funcoes e encarregados de educacao.
- Adicionada configuracao individual para exigir ou nao a configuracao de MFA.
- Utilizadores existentes sem MFA nao ficam obrigados a configurar MFA apos a
  migration; utilizadores novos mantem a exigencia por defeito.
- Reforcados os bloqueios de login, reset de password, MFA pendente e alunos
  associados para contas sem autorizacao.
- Documentada a utilizacao de `INITIAL_ADMIN_PASSWORD` em `DEPLOY.md`.

## 2026-08-19

### Documentacao

- Criado `README.md` com stack, setup, comandos uteis, regras funcionais e notas de seguranca.
- Criado `CHANGELOG.md` para registar contexto tecnico e alteracoes relevantes.

### Dashboard do Professor

- Criada dashboard customizada em `app/Filament/Pages/Dashboard.php`.
- A dashboard do painel agora usa uma coluna para evitar widgets lado a lado na home do professor.
- `AdminPanelProvider` passou a usar `App\Filament\Pages\Dashboard`.

### Pagina About

- `app/Filament/Pages/AboutSystem.php` passou a estar visivel apenas para `Super Admin`.
- O acesso direto tambem foi bloqueado via `canAccess()`.

### Horario Sobreposto

- `app/Filament/Pages/HorarioSobreposto.php` passou a controlar acesso por cargo de coordenador, nao por role.
- O cargo de coordenador e validado pela relacao `teacher_positions`/`positions` no ano letivo ativo.
- `Super Admin` continua com acesso administrativo.
- Coordenadores so veem docentes do proprio departamento.
- A lista de docentes so inclui docentes com horarios no ano letivo ativo e estado `Aprovado` ou `Aprovado DP`.
- A selecao de docentes e filtrada no backend para impedir manipulacao de IDs no formulario.
- `app/Services/MergedScheduleCalendarService.php` passou a considerar apenas ano letivo ativo e estados aprovados.
- `resources/views/components/schedule-grid-merged.blade.php` foi refeito para mostrar todos os horarios no mesmo slot, evitando que uma aula esconda outra por causa de `rowspan`.

### Relacoes e Inscricoes

- `app/Models/Schedule.php`: `registrationSubjects()` passou a usar relacao direta por `id_schedule`.
- `app/Filament/Resources/RegistrationSubjectResource.php`: formulario ficou seguro quando nao existe `$record`.
- A contagem de vagas passou a excluir o proprio registo em edicao.

### ScheduleResource

- Removida colisao entre dois campos com o mesmo estado `shift`.
- O turno automatico passou a ser exibido como `Placeholder`, sem ser enviado no submit.

### WeeklyScheduleWidget

- Queries de notificacoes passaram a ser filtradas por professor e ano letivo ativo.

### Validacao

- `php artisan test`: 29 testes a passar.
- `php -l`: sem erros nos ficheiros PHP alterados durante a sessao.
- `composer audit`: sem vulnerabilidades conhecidas no momento da analise.
- `npm audit`: sem vulnerabilidades conhecidas no momento da analise.

### Nota Sobre Formato

- `./vendor/bin/pint --test` acusa muitos problemas de estilo pre-existentes em varias partes do projeto.
- Nao foi aplicado `pint` global para evitar alterar ficheiros nao relacionados.

### Validacao de Dependencias

- Foi tentado atualizar `althinect/filament-spatie-roles-permissions` de `2.3.2` para `2.3.3`.
- A versao `2.3.3` foi rejeitada por incompatibilidade com o Filament atual: assinatura de `getNavigationGroup()` diferente da esperada por `Filament\Resources\Resource`.
- O pacote ficou fixado em `2.3.2`, que passa `composer audit` e permite `artisan package:discover`.
- Dependencias npm foram atualizadas dentro das constraints atuais do projeto.
- Assets publicados do Filament foram atualizados por `php artisan filament:upgrade` durante o fluxo Composer.
- Validacoes apos refresh: `composer audit`, `npm audit`, `npm run build` e `php artisan test` passaram.

### Coerencia de Horarios de Alunos

- Adicionadas migrations para `registrations_subjects.id_schedule` e `students.number` como string.
- `RegistrationSubject::selectedSchedule()` passou a usar `id_schedule` de forma explicita.
- `Student::schedules()` passou a usar a pivot correta `schedules_students`.
- Exportacao/listagem de alunos por professor passou a resolver horarios por pivot de alunos em vez de procurar numeros dentro de texto livre.
- Adicionado teste de regressao para garantir associacao exata entre aluno, horario e turno selecionado.

### Organizacao Git

- A branch `verHorariosAdmin` foi integrada em `main`.
- A branch `verHorariosAdmin` foi removida localmente e no remoto.
- `main` ficou sincronizada com `origin/main`.
- Estado validado com 29 testes, `composer audit` e `npm audit` sem vulnerabilidades conhecidas.
- Permanecem ficheiros locais nao versionados para revisao futura; nao foram apagados para evitar perda de trabalho.
