<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
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

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyNeverLoggedIn(): void
    {
        $this->resetPage();
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
            ->orderByRaw('last_login_at IS NOT NULL')
            ->orderBy('name');
    }
}
