<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Estado de preparação do ano letivo</x-slot>
        <x-slot name="description">Verificação rápida das condições necessárias para iniciar as marcações.</x-slot>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($checks as $check)
                @php
                    $color = match ($check['status']) {
                        'Concluído' => 'success',
                        'Bloqueante' => 'danger',
                        default => 'warning',
                    };
                @endphp
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-semibold text-gray-950 dark:text-white">{{ $check['name'] }}</h3>
                        <x-filament::badge :color="$color">{{ $check['status'] }}</x-filament::badge>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $check['description'] }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
