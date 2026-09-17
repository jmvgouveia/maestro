# Estado Atual

Data: 2026-09-17

## Tarefa

Corrigir a lista "Os meus Alunos" para mostrar apenas alunos matriculados no
horário/turno do professor autenticado.

## Alteração concluída

`app/Filament/Resources/TeacherStudentsResource.php` deixou de incluir alunos
apenas por coincidirem na mesma turma e disciplina. A consulta agora aceita:

- `registrations_subjects.id_schedule` ligado a um horário aprovado do professor;
- associação explícita em `schedules_students` num horário aprovado do professor.

O filtro por turma inteira foi removido porque fazia professores diferentes
receberem a mesma lista, mesmo quando o aluno estava matriculado noutro turno.

## Validação

- `ddev exec vendor/bin/phpunit tests/Feature/ScheduleStudentAssignmentTest.php`
  passou: 8 testes, 26 asserções.
- DDEV está ativo com MariaDB.
- BD ativa: ano letivo ID 1, 3 professores com horários aprovados, 3
  associações explícitas de alunos a horários e 1 matrícula com `id_schedule`.

## Próximo passo possível

Se necessário, consultar na BD as linhas concretas de `registrations_subjects`,
`schedules`, `schedules_students` e `schedules_classes` para confirmar os
alunos/turnos de cada professor.
