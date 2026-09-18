<x-filament::page>
    <div class="flex justify-end mb-4">
        <x-filament::button wire:click="exportMusicalGroups" icon="heroicon-o-arrow-down-tray">
            Exportar CSV
        </x-filament::button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left">Disciplina</th>
                    <th class="px-4 py-3 text-left">Professor</th>
                    <th class="px-4 py-3 text-left">Turma</th>
                    <th class="px-4 py-3 text-left">Turno</th>
                    <th class="px-4 py-3 text-right">Limite</th>
                    <th class="px-4 py-3 text-right">Inscritos</th>
                    <th class="px-4 py-3 text-right">Vagas</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $index => $row)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-4 py-3">{{ $row['subject'] }}</td>
                        <td class="px-4 py-3">{{ $row['teacher'] }}</td>
                        <td class="px-4 py-3">{{ $row['class'] }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $row['shift'] }}</div>
                            <div class="text-xs text-gray-500">{{ collect($row['slots'])->map(fn ($slot) => $slot['day'].' '.$slot['time'].' - '.$slot['room'])->implode(' | ') }}</div>
                        </td>
                        <td class="px-4 py-3 text-right">{{ $row['limit'] ?? 'Sem limite' }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['enrolled'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['available'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <x-filament::button size="sm" wire:click="showMusicalStudents({{ $index }})">
                                Alunos
                            </x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="exportMusicalGroup({{ $index }})">
                                CSV
                            </x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Não existem turnos disponíveis.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($selectedMusicalGroup !== null && isset($this->rows[$selectedMusicalGroup]))
        @php($selected = $this->rows[$selectedMusicalGroup])
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click="closeMusicalStudents">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900" wire:click.stop>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Alunos inscritos</h2>
                        <p class="text-sm text-gray-500">{{ $selected['subject'] }} · {{ $selected['class'] }} · {{ $selected['shift'] }}</p>
                    </div>
                    <x-filament::button color="gray" wire:click="closeMusicalStudents">Fechar</x-filament::button>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead><tr><th class="px-3 py-2 text-left">Número</th><th class="px-3 py-2 text-left">Aluno</th></tr></thead>
                        <tbody>
                            @forelse ($selected['students'] as $student)
                                <tr class="border-t border-gray-200 dark:border-gray-700"><td class="px-3 py-2">{{ $student['number'] }}</td><td class="px-3 py-2">{{ $student['name'] }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="px-3 py-4 text-center text-gray-500">Nenhum aluno inscrito.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>
