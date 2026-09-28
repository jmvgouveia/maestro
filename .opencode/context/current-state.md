# Current State

## Current Work

- Reorganização da navegação Filament dos módulos `Salas` e `Gestão de Chaves`.
- A solução usa a navegação nativa do Filament 3.3.54, incluindo `navigationParentItem`.
- Foi criado um único grupo visual `GESTÃO DE CHAVES`, com os itens-pai `Operação`, `Consulta` e `ADMINISTRAÇÃO` e os respetivos submenus.
- Os itens-pai têm URLs para a primeira página de cada submenu e ficam ativos em todas as rotas dos respetivos filhos.

## Consolidation Decision

- `RoomResource` será a única entrada `Salas` da administração do módulo.
- A tabela de salas passa a mostrar características e visibilidade no mapa.
- A configuração individual e bulk de ocupação/características foi integrada no `RoomResource`.
- `KeyControlRoomSettings` mantém a URL e permissões, mas deixou de aparecer na navegação para evitar duplicação.
- `RoomFeatureSettings` continua como `Características das salas`.

## Final Navigation Intent

- `GESTÃO DE CHAVES`
  - `Operação`
    - `Controlo de Chaves`
    - `Devoluções pendentes`
    - `Mapa de ocupação`
  - `Consulta`
    - `Movimentos`
    - `Histórico`
  - `Definições`
    - `Características`
    - `Porteiro`
    - `Relatórios`
    - `Sala`

## Files Changed In Current Uncommitted Navigation Work

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Filament/Pages/RoomOccupancyMap.php`
- `app/Filament/Pages/KeyControlOperation.php`
- `app/Filament/Pages/KeyControlPendingReturns.php`
- `app/Filament/Pages/KeyControlAudit.php`
- `app/Filament/Pages/KeyControlSettings.php`
- `app/Filament/Pages/KeyControlRoomSettings.php`
- `app/Filament/Pages/RoomFeatureSettings.php`
- `app/Filament/Resources/KeyControlResource.php`
- `app/Filament/Resources/RoomResource.php`
- `app/Filament/Resources/UserBuildingAuthorizationResource.php`
- `tests/Feature/ExampleTest.php`

## Repository / Deployment

- Commit `971e963` is on `origin/main` and was deployed previously.
- Commit `ce6dc71` (`permite porteiro consultar movimentos proprios`) exists locally; push failed because GitHub SSH authentication was unavailable in this environment.
- Current navigation changes are uncommitted on top of `ce6dc71`.
- The latest navigation change consolidates the room/key-control entries into one native Filament group with nested parent items.
- A correção mais recente tornou os itens-pai clicáveis; a árvore abre ao navegar para a primeira página de cada submenu.
- Production is still expected to be updated from `main` after the pending changes are committed/pushed.
- No migration, model, relation or database change was made for the navigation task.

## Validation

- Before the navigation task, full suite passed: 148 tests, 517 assertions.
- Current `git diff --check` passes.
- Focused validation passed with DDEV available:
  - `ddev php artisan test tests/Feature/ExampleTest.php` (9 tests, 32 assertions)
- `ddev php artisan optimize:clear`
  - `ddev php artisan optimize`
- Migration `2026_09_28_120000_add_student_key_alert_limit_to_rooms` foi aplicada apenas na BD local DDEV `meu-horario-db`; a coluna nullable foi confirmada.

## Student Occurrence Report

- Criada a página `app/Filament/Pages/KeyControlOffenders.php` e a view `resources/views/filament/pages/key-control-offenders.blade.php`.
- A página aparece em `Consulta` como `Alunos com mais ocorrências` e exige `view key control history`; exportação exige adicionalmente `export key control`.
- Uma ocorrência é derivada quando o aluno ultrapassa o limite configurado para a sala ou o limite global, por defeito 120 minutos, esteja a chave devolvida ou ainda pendente.
- O relatório mostra ocorrências, pendentes, atraso total e maior atraso após o limite, com filtros de datas, sala e pesquisa do aluno.
- A query aplica o âmbito de edifícios autorizados e o CSV reutiliza o mesmo resultado filtrado, neutralizando valores perigosos para fórmulas CSV.
- Validação: `git diff --check` passou. `php -l` não foi executado porque o binário `php` não está disponível diretamente no ambiente.
- Corrigido `SQLSTATE[HY093]` na query do relatório: bindings posicionais em `selectRaw()`/join estavam a ser associados às colunas erradas; expressões agora usam apenas valores seguros interpolados e tipados. `ddev php -l` passou.
- Com autorização explícita, foram inseridos dados fictícios na BD local DDEV: aluno `990001` (`Aluno Teste Ocorrencias`), movimentos `KeyControl` IDs `5` (devolvido com atraso) e `6` (pendente com atraso), usando limite global local de 120 minutos. Não foram apagados dados.
- A regra do relatório foi alterada: ocorrências deixam de ser inferidas retroativamente por duração. `KeyControlOperation::hasStudentKeyAlert()` cria uma ocorrência `student_key_alert` quando o cartão deteta o limite efetivo da sala; o relatório agrega esses eventos. Os fluxos de devolução normal, devoluções pendentes e fim de acesso por chave de piso atualizam o evento com o atraso após o alerta.
- Corrigido o filtro polimórfico do relatório: a classe `App\Models\Student` estava interpolada em SQL bruto e as barras invertidas impediam o `JOIN`. O filtro usa binding normal. O relatório agora também mostra cada ocorrência com recebimento, alerta, devolução e atraso; validação local encontrou a ocorrência da Daniela em `Sala T1.01` (14:45, alerta 14:46, devolução 14:50, 3 minutos).
- UX do relatório ajustada: ranking limitado aos 5 primeiros e situação apresentada como `pendente(s)` ou `Regularizado`; detalhe ficou como lista de ocorrências com selector de 10/25/50/100 registos e paginação. Query local validada com 8 ocorrências.
- Ordenação estabilizada com alerta + ID como desempate, para mudar o selector não reordenar a lista. O ranking permite escolher `Mais ocorrências` ou `Maior atraso acumulado`; a lista de detalhe mantém ordenação independente e estável. Sintaxe, `git diff --check` e consulta local validados.
- A pesquisa de aluno foi movida para o cabeçalho da lista de detalhe, no lado direito. O selector de linhas foi movido para o rodapé, acompanhado da contagem e paginação; filtros de data/sala permanecem no topo. A mudança de filtros reinicia a página de detalhe.
- Os nomes dos alunos no ranking e no detalhe passaram a ser links para `Histórico`, com o filtro do aluno já preenchido via query string (`person`). Filtro URL e consulta foram validados localmente; para o aluno de teste retornaram 8 eventos.
- No `Histórico`, o evento `student_key_alert` passou a aparecer como `Tempo de devolução ultrapassado` e usa a cor de alerta vermelha (`danger`). Validação do label/cor concluída.
- Corrigido o âmbito de `KeyControlResource`: utilizadores com role `Porteiro` mas também com `view-any key control` (Gestão/Admin) deixam de ser filtrados pelos próprios registos; apenas o porteiro operacional sem essa permissão vê os seus movimentos.

## Next Safe Action

1. Review the resulting Filament navigation visually.
2. Commit and push `ce6dc71` if not already pushed, plus the current navigation changes.
3. On production, run `git pull --ff-only origin main`, `php artisan migrate --force` only if pending migrations exist, then `php artisan optimize:clear` and `php artisan optimize`.
