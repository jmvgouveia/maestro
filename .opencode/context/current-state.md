# Current State

## Completed

- Added `TeacherSubjectShiftAudit` under the Filament `Auditoria` navigation group.
- The audit is restricted to the active school year and schedule statuses `Aprovado` and `Aprovado DP`.
- It displays one row per schedule/class combination and supports filters for teacher, class, subject, building and shift.
- Added CSV export and a dedicated permission: `view teacher subject shift audit`.
- Granted the permission to the `Auditoria` role in the seeder and in a new migration for existing installations.
- Added the access-control assertion to `AccessAuditTest`.

## Verification

- `git diff --check` passes.
- PHP syntax checks and PHPUnit could not run because `php` is not installed in the current environment.

## Files

- `app/Filament/Pages/TeacherSubjectShiftAudit.php`
- `resources/views/filament/pages/teacher-subject-shift-audit.blade.php`
- `database/migrations/2026_09_18_170000_add_teacher_subject_shift_audit_permission.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `database/migrations/2026_09_18_160000_create_auditoria_role.php`
- `tests/Feature/AccessAuditTest.php`
