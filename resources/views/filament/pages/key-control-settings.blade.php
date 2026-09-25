<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Guardar definições</x-filament::button>
    </form>

    <section class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-5 flex items-center justify-between gap-4">
            <div><p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Confirmação</p><h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">Resumo dos destinatários</h2></div>
            <x-heroicon-o-envelope class="h-6 w-6 text-gray-400" />
        </div>
        @if ($this->recipientSummary === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Ainda não existem destinatários configurados.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($this->recipientSummary as $recipient)
                    <div class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 md:flex-row md:items-start md:justify-between">
                        <div><div class="flex flex-wrap items-center gap-2"><strong class="text-sm text-gray-950 dark:text-white">{{ $recipient['name'] }}</strong><span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-950/50 dark:text-blue-200">{{ $recipient['type'] }}</span><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $recipient['active'] ? 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">{{ $recipient['active'] ? 'Ativo' : 'Inativo' }}</span></div><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $recipient['email'] }}</p></div>
                        <div class="text-left md:max-w-md md:text-right"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Relatórios associados</p><p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ implode(', ', $recipient['reports']) ?: 'Nenhum relatório' }}</p></div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-filament-panels::page>
