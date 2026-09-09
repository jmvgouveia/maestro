<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Bem-vindo à Ajuda do Maestro</x-slot>
            <x-slot name="description">Consulte as orientações para utilizar as principais funcionalidades da sua área.</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Se não encontrar resposta à sua dúvida, contacte a secretaria ou o responsável pelo sistema.
            </p>
        </x-filament::section>

        <div class="grid gap-6 md:grid-cols-2">
            @forelse ($this->getArticles() as $article)
                <x-filament::section :heading="$article->title" icon="heroicon-o-information-circle">
                    <div class="whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $article->content }}</div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-400">A informação de ajuda será disponibilizada brevemente.</p>
                </x-filament::section>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
