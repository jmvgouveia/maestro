# Current Project State

Last updated: 2026-09-06

## Current Objective

Manter um state of the art global do projeto e evoluir os pontos fracos por
ordem de risco.

## Current Task

Preparar o projeto para produção, sem alterar a lógica de negócio nesta fase.
Foi decidido aceitar temporariamente a limitação de não distinguir o mesmo
aluno em duas inscrições da mesma disciplina no mesmo horário.

Tambem foi completado o importer de docentes com campos relacionais opcionais.
Foi corrigido o menu lateral do docente: `TeacherSubjectResource` aparece como
"As minhas disciplinas" no grupo `Horários`, abaixo de "O Meu Horário". A
pagina continua a mostrar a grelha, com estado explicito quando nao existem
periodos horarios.

## Current State

O state of the art global esta documentado em
`.opencode/context/project-state-of-art.md`.

Cada turma tem edificios permitidos configuraveis na pivot `class_buildings`.
O edificio do horario e derivado da sala (`schedules.id_room` ->
`rooms.id_building`). O formulario filtra turmas por curso e edificio permitido
e a validacao server-side verifica todas as turmas selecionadas.

Migration criada: `database/migrations/2026_09_02_130000_remove_building_from_classes_table.php`.
Importador e recurso de turmas usam `id_buildings`; `id_building` nao e mais
coluna de `classes`.

O backlog global continua em `.opencode/context/project-state-of-art.md`; o
bloqueio P0 de `Subject.type` e os restantes riscos permanecem por tratar.

Tambem existem alteracoes de configuracao do OpenCode em `AGENTS.md` e no
agente `.opencode/agents/continuity.md`, bem como os ficheiros de contexto.
Estas alteracoes ja estavam presentes no repositorio ao iniciar esta sessao e
nao foram revertidas.

## Relevant Files

- `.opencode/context/project-state-of-art.md`: relatorio e backlog global.
- `app/Filament/Imports/StudentImporter.php`: correcao de dados do importador.
- `app/Filament/Imports/TeacherImporter.php`: importer docente com datas,
  utilizador, role e FKs relacionais por ID/nome.
- `tests/Feature/TeacherImporterTest.php`: teste do importer docente.
- `app/Filament/Widgets/WeeklyScheduleWidget.php` e a respetiva view: query de
  horarios e fallback visivel sem periodos.
- `app/Filament/Resources/TeacherSubjectResource.php`: navegacao do docente no
  grupo Horários, com escopo ao ano ativo.
- `app/Models/Classes.php`, `app/Filament/Resources/ClassesResource.php` e
  `app/Filament/Imports/ClassesImporter.php`: edificios permitidos por turma;
  aceita `id_edificio` e `id_buildings`.
- `app/Filament/Resources/ScheduleResource.php`: turmas elegiveis sem filtro
  indevido e com filtro por edificios permitidos.
- `app/Filament/Resources/ScheduleResource/Traits/ValidatesClassBuildings.php`:
  validacao server-side turma/sala.
- `tests/Feature/ClassesImporterTest.php`: teste da importacao para a pivot.
- `tests/Feature/ScheduleStudentAssignmentTest.php`: cobertura de dois
  edificios por turma.
- `app/Observers/StudentObserver.php`: alunos sem email nao criam conta.
- `.ddev/config.yaml` e `.env`: queue assíncrona com worker local.
- `app/Filament/Imports/SubjectImporter.php` e `app/Models/Subject.php`:
  alteracao pendente e campo importavel confirmado.
- `app/Filament/Imports/TimePeriodImporter.php`: referência corrigida para o
  modelo existente `Timeperiod` (sensibilidade a maiúsculas/minúsculas).
- `AGENTS.md`, `.opencode/agents/continuity.md` e `.opencode/context/`:
  configuracao de continuidade.

## Completed

Foram concluidas auditorias estaticas de arquitetura, Laravel/Filament,
seguranca, qualidade e continuidade. O relatorio foi persistido.
`StudentImporter`, `StudentObserver` e o modelo turma-edificio foram corrigidos
e formatados. A restricao parametrizavel por turma foi implementada.
Foi corrigido o preenchimento indevido de `id_buildings` no importador de
turmas; o campo agora sincroniza apenas `class_buildings`.
`TeacherImporter` foi completado para importar relações opcionais e criar o
utilizador apenas depois da validação da linha.
O item "As minhas disciplinas" agora fica abaixo de "O Meu Horário" e a lista
continua filtrada por docente/ano ativo. A grelha mostra aviso quando
`timeperiods` esta vazio.
Foi aplicada a preparacao de producao: autorizacao server-side para acoes de
ativacao, validacao de pedidos de troca, preservacao de turnos, neutralizacao
de formulas em export CSV, validacao dos intervalos de inscricao, relacao
SchoolYear-students corrigida e selecao do ano anterior por data.
Foram adicionados exemplos visíveis no importer para todos os campos, incluindo
formatos de data e valores relacionais por nome.

## Open Issues

No host nao existe PHP, mas DDEV fornece PHP 8.3 e MariaDB 10.11.
DDEV confirma `queue.default=database` e worker ativo.
`StudentImporter.php` e `StudentObserver.php`: lint e Pint passaram.
Suite Laravel: 96 passaram (335 assertions), incluindo o `DashboardTest`
alinhado com o redirecionamento atual para `/maestro`.
Testes apos a migration: 16 passaram (80 assertions), incluindo horarios,
historico e importacao de alunos.
Testes de importacao de turmas e horarios: 7 passaram (22 assertions).
Testes de importadores e autorizacao: 13 passaram (52 assertions).
Testes de acesso e grelha docente: 14 passaram (46 assertions).
Teste de navegacao docente: 9 passaram (28 assertions).
Teste de importacao de periodos: 19 passaram (85 assertions) no conjunto focado.
`git diff --check` passou.

Auditoria de produção identificou como prioritários: autorização server-side
nas ações de ativação de utilizadores e no modal de pedidos de troca; a falha
do `DashboardTest`; validação de integridade de `registrations_subjects`;
preservação de turnos ao editar horários com alunos; validações de datas e
algumas relações Eloquent suspeitas.

## Blockers

Nao existem falhas na suite completa atual. A limitação de distinguir o mesmo
aluno em duas inscrições da mesma disciplina no mesmo horário foi aceite para
este lançamento e está registada em `decisions.md` como `DEC-001`.
O log antigo confirma a colisao de email auxiliar no `StudentObserver`; a
causa foi corrigida, mas nao foi feita uma importacao manual em dados reais.
Testes de horarios/historico e edificios: 16 passaram (80 assertions),
incluindo uma turma em dois edificios. A migration foi aplicada no DDEV de
testes; `classes.id_building` nao existe e `class_buildings` existe.
O worker DDEV esta `RUNNING`; os 3 jobs antigos e os failures correspondentes
foram limpos sem retry para evitar duplicacoes.
As tabelas `classes` e `class_buildings` foram limpas no banco DDEV de testes
para permitir novo import; cursos, alunos, edificios e horarios foram
preservados.

## Validations Already Performed

`git diff --check`, lint PHP e Pint passaram para `StudentImporter.php`.

## Working Memory

### Known Good

O backlog esta no relatorio, com IDs `SOT-001` a `SOT-010` e prioridades P0-P2.
Importacao de alunos: data/genero/email corrigidos; falta teste dedicado.
Importacao de turmas: campos `id_edificio`/`id_buildings` sincronizam a pivot
sem erro de coluna.
Importacao de docentes: campos opcionais relacionais aceitam ID ou nome; teste
passou com utilizador e role Professor.
O importer docente tem exemplos para preencher o template CSV.
No banco DDEV atual existem 7 dias, 0 periodos e 2 disciplinas atribuidas ao
docente ativo; por isso nao ha linhas de grelha ate importar/configurar
`timeperiods`.

### Known Bad

Ver `project-state-of-art.md` para evidencias, impacto e recomendacoes sem
perder a distincao entre bugs confirmados e riscos ainda dependentes de runtime.

### Do Not Retry

Nao repetir testes no host; usar DDEV para validacoes PHP.

### Current Hypothesis

O teste dedicado ao `StudentImporter` passou (inclui email vazio). O teste de
turma em dois edificios passou. Os dados antigos de `classes.id_building` foram
descartados no banco DDEV de testes conforme autorizado; em bases existentes a
migration faz backfill antes de remover a coluna.

## Next Action

### Security priorities for next session

1. Preparar patches sem mudanca major: Laravel 12.69.1, Filament 3.3.55 e
   plugin de permissoes 2.3.3, com revisao do lockfile e testes focados.
2. Decidir e preparar a remocao das dependencias sem uso confirmado, sem
   misturar esta limpeza com upgrades major.
3. Definir auditorias automaticas de `composer audit --locked` e `npm audit`,
   com resposta urgente a RCEs e vulnerabilidades no catalogo CISA KEV.
4. Rever o hardening do novo servidor: PHP-FPM sem escrita no codigo, escrita
   apenas em `storage/` e `bootstrap/cache/`, PHP apenas por `public/index.php`,
   sem execucao em `public/storage`, SSH por chave, root remoto desativado,
   firewall, Fail2ban, updates de seguranca e monitorizacao de cron/systemd/
   Supervisor/`authorized_keys`.
5. Preparar PHP 8.5 em staging; deixar Filament 5, Livewire 4 e Laravel 13 para
   uma migracao posterior e faseada, com testes de permissoes, MFA,
   impersonation e importadores.

Contexto do incidente: o servidor anterior foi eliminado; o servidor e a
instalacao atuais sao novos e usam credenciais novas. Nao ha evidencia forense
remanescente. Livewire 3.6.3, presente historicamente ate 2026-08-19, e uma
hipotese plausivel devido ao CVE-2025-54068 (RCE sem autenticacao), mas nao pode
ser confirmado como causa.

Depois destas prioridades, testar no painel a configuracao de edificios
permitidos por turma, incluindo CPAEI em Sede/SMT. Tratar `SOT-001` e
`SOT-003`, seguido de `SOT-002`/`SOT-005`.

## Important Constraints

- Treat the repository as the source of truth for implementation state.
- Do not persist secrets or credentials in continuity files.
- Replace obsolete information instead of accumulating contradictory state.
