<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessAudit extends Page
{
    use WithPagination;

    protected static string $view = 'filament.pages.access-audit';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Auditoria';

    protected static ?string $navigationLabel = 'Acessos';

    protected static ?string $title = 'Auditoria de acessos';

    protected static ?string $slug = 'auditoria-acessos';

    public string $search = '';

    public bool $onlyNeverLoggedIn = false;

    public ?int $roleFilterId = null;

    public string $sortColumn = 'name';

    public string $sortDirection = 'asc';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view access audit') ?? false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyNeverLoggedIn(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilterId(): void
    {
        $this->resetPage();
    }

    public function showAllUsers(): void
    {
        $this->onlyNeverLoggedIn = false;
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', 'email', 'roles', 'last_login_at'], true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function roleOptions(): array
    {
        return Role::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getUsersProperty(): LengthAwarePaginator
    {
        return $this->usersQuery()->paginate(25);
    }

    public function exportUsers(): StreamedResponse
    {
        $users = $this->usersQuery()->get();

        return response()->streamDownload(function () use ($users): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nome', 'E-mail', 'Funções', 'Último acesso'], ';');

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    $user->roles->pluck('name')->implode(', '),
                    $user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca entrou',
                ], ';');
            }

            fclose($handle);
        }, 'auditoria-acessos.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function usersQuery()
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->when($this->onlyNeverLoggedIn, fn ($query) => $query->whereNull('last_login_at'))
            ->when($this->roleFilterId, fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($this->roleFilterId)))
            ->when($this->sortColumn === 'roles', function ($query): void {
                $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';
                $query->orderByRaw(
                    "(SELECT MIN(audit_roles.name) FROM roles AS audit_roles INNER JOIN model_has_roles AS audit_model_roles ON audit_model_roles.role_id = audit_roles.id WHERE audit_model_roles.model_id = users.id AND audit_model_roles.model_type = ?) {$direction}",
                    [User::class],
                );
            })
            ->when($this->sortColumn !== 'roles', fn ($query) => $query->orderBy($this->sortColumn, $this->sortDirection));
    }
}
