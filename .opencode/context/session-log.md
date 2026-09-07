# Session Log

This file contains a lightweight operational history of meaningful work sessions.

Keep entries short.

Do not copy conversations.

Do not copy full command outputs.

Do not copy complete diffs.

Do not record trivial interactions.

---

## Entry Format

### YYYY-MM-DD HH:MM

Worked on:

- Main task or area.

Changed:

- Meaningful changes completed.

Discovered:

- Important findings, if any.

Validated:

- Relevant tests or checks performed.

Remaining:

- Relevant unfinished work.

Checkpoint:

- `current-state.md` updated.

---

## Sessions

### 2026-09-07

Worked on:

- Revisão de produção do fluxo de pedidos de troca e carga horária.

Changed:

- Corrigida a seleção da primeira marcação do slot por ano letivo, sala, dia,
  período, `created_at` e `id`.
- Reforçada a validação server-side do conflito no fluxo de criação.
- Protegido o contador contra relações `Schedule` nulas.
- Corrigido o importador de contadores para a carga padrão 22/4/26 e ano ativo.

Discovered:

- O recálculo ao editar um professor mistura potencialmente anos letivos,
  atualiza apenas a componente letiva e pode reescrever cargos/reduções
  históricos.

Validated:

- `git diff --check` passou.
- Testes PHP não executados neste ambiente porque o comando `php` não está instalado.

Remaining:

- Rever `Teacher::updateHourCounterFromReductions()` e `EditTeacher` após o
  primeiro período de utilização em produção, conforme `DEC-002`.
- Confirmar novo envio após remover o registo duplicado dos listeners de email.

Checkpoint:

- `current-state.md` atualizado.

---

### 2026-09-03

Worked on:

- Decisão de preparação para produção e limitação temporária de inscrições
  duplicadas no mesmo horário.

Changed:

- Registada a decisão `DEC-001`.

Discovered:

- A distinção correta exige usar a identidade de `registration`, não apenas
  `student`, mas foi adiada para depois do lançamento.

Validated:

- Auditorias de arquitetura, segurança e qualidade concluídas.
- Suite completa: 96 testes passaram e 336 asserções.
- Lint PHP e `git diff --check` passaram nos ficheiros críticos.

Remaining:

- Executar testes manuais de aceitação e preparar o deploy.
- Manter a limitação `DEC-001` documentada para correção posterior.

Checkpoint:

- `current-state.md` atualizado.
