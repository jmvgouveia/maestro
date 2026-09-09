<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ $isStudent ? 'Marcação de horários' : 'Períodos de marcação de horários' }}
        </x-slot>

        @if (! $schoolYear)
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Não existe um ano letivo ativo configurado.
            </p>
        @else
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Ano letivo {{ $schoolYear->schoolyear }}
            </p>

            <div class="grid gap-3 md:grid-cols-3">
                @foreach ($periods as $period)
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $period['name'] }}</h3>
                            <x-filament::badge :color="$period['statusColor']">
                                {{ $period['status'] }}
                            </x-filament::badge>
                        </div>
                        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $period['dates'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $period['message'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
