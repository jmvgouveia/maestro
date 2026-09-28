# Project Decisions

This file contains significant architectural, domain and implementation decisions that may affect future work.

Do not use this file for ordinary progress tracking.

Do not record trivial implementation choices.

## Decision Format

Use:

### DEC-XXX — Short title

Date: YYYY-MM-DD

Status: Proposed | Active | Superseded | Rejected

Decision:

Describe the decision concisely.

Reason:

Describe why the decision was made when known.

Consequences:

- Relevant consequence.
- Relevant constraint.

Supersedes:

DEC-XXX, when applicable.

---

## Active Decisions

### DEC-003 — Consolidar cadastro e configuração de salas

Date: 2026-09-28

Status: Active

Decision:

Usar `RoomResource` como fonte única de salas e integrar na sua tabela a
configuração já existente de mapa de ocupação e características. Manter
`KeyControlRoomSettings` acessível pela URL e permissões existentes, mas sem
item próprio na navegação.

Reason:

Evitar duas entradas administrativas para a mesma entidade `Room` e tornar
clara a relação entre o cadastro da sala e as suas configurações.

Consequences:

- Não alterar migrations, models, relações ou base de dados.
- Manter as ações de configuração protegidas por `manage room occupancy settings`.
- A navegação usa um grupo Filament nativo com `navigationParentItem`, sem sidebar Blade customizada.

---

### DEC-001 — Adiar distinção de inscrições duplicadas no horário

Date: 2026-09-03

Status: Active

Decision:

Para o lançamento em produção, aceitar temporariamente a limitação de o mesmo
aluno não poder ser distinguido entre duas inscrições da mesma disciplina em
turmas/cursos diferentes no mesmo horário.

Reason:

O lançamento está previsto para os próximos dias e a alteração correta exige
uma mudança estrutural na associação entre horário e inscrição.

Consequences:

- Não adicionar agora `id_registration` a `schedules_students`.
- Alunos diferentes em turmas diferentes continuam a ser suportados.
- A distinção por inscrição será tratada numa fase posterior, com migration,
  atualização dos fluxos de gravação e testes.

---

### DEC-002 — Adiar correção do recálculo de carga horária

Date: 2026-09-07

Status: Active

Decision:

Para o primeiro período de utilização em produção, manter a lógica atual de
recálculo da carga horária e adiar a revisão de `Teacher::updateHourCounterFromReductions()`.

Reason:

Todos os professores usam a carga base padrão de 22 horas letivas e 4 horas
não letivas. A alteração estrutural do recálculo não é crítica para a primeira
entrada de dados e será reavaliada após existir histórico real de utilização.

Consequences:

- Não alterar agora o fluxo de edição de professores.
- Manter para revisão a filtragem por ano letivo das reduções.
- Manter para revisão o recálculo da componente não letiva, total e horários já aprovados.
- Rever também o comportamento de `EditTeacher`, que atualmente reatribui cargos
  e reduções ao ano letivo ativo.

---

---

## Superseded Decisions

No superseded decisions recorded yet.

---

## Rules

- Never delete historical decisions merely because they changed.
- Mark replaced decisions as `Superseded`.
- Record the replacement decision separately.
- Do not invent reasons that were not established.
- Keep entries concise.
- Do not store secrets or credentials.
