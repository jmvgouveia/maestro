<x-filament::page>
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
            <div>
                <label for="access-search" class="mb-1 block text-sm font-medium">Pesquisar</label>
                <input id="access-search" type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Nome ou e-mail"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 md:w-72">
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" wire:model.live="onlyNeverLoggedIn"
                    class="rounded border-gray-300 text-primary-600 shadow-sm">
                <span>Nunca entrou na plataforma</span>
            </label>
            <x-filament::button color="gray" size="sm" wire:click="showAllUsers">
                Todos
            </x-filament::button>
        </div>

        <x-filament::button wire:click="exportUsers" icon="heroicon-o-arrow-down-tray">
            Exportar CSV
        </x-filament::button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left"><button type="button" wire:click="sortBy('name')">Nome
                            @if ($sortColumn === 'name') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif</button></th>
                    <th class="px-4 py-3 text-left"><button type="button" wire:click="sortBy('email')">E-mail
                            @if ($sortColumn === 'email') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif</button></th>
                    <th class="px-4 py-3 text-left">Funções</th>
                    <th class="px-4 py-3 text-left"><button type="button" wire:click="sortBy('last_login_at')">Último acesso
                            @if ($sortColumn === 'last_login_at') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif</button></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->users as $user)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($user->last_login_at)
                                {{ $user->last_login_at->format('d/m/Y H:i') }}
                            @else
                                <span class="font-medium text-warning-600">Nunca entrou</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">Não existem utilizadores.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->users->links() }}
    </div>
</x-filament::page>
