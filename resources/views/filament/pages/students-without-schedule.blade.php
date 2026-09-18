<x-filament::page>
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
            <div>
                <label for="students-without-schedule-search" class="mb-1 block text-sm font-medium">Pesquisar</label>
                <input id="students-without-schedule-search" type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Aluno, número ou disciplina"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 md:w-80">
            </div>
            <div>
                <label for="students-without-schedule-building" class="mb-1 block text-sm font-medium">Núcleo</label>
                <select id="students-without-schedule-building" wire:model.live="buildingFilterId"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 md:w-60">
                    <option value="">Todos os núcleos</option>
                    @foreach ($this->buildingOptions() as $buildingId => $buildingName)
                        <option value="{{ $buildingId }}">{{ $buildingName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="students-without-schedule-class" class="mb-1 block text-sm font-medium">Turma</label>
                <select id="students-without-schedule-class" wire:model.live="classFilterId"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 md:w-60">
                    <option value="">Todas as turmas</option>
                    @foreach ($this->classOptions() as $classId => $className)
                        <option value="{{ $classId }}">{{ $className }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="students-without-schedule-subject" class="mb-1 block text-sm font-medium">Disciplina</label>
                <select id="students-without-schedule-subject" wire:model.live="subjectFilterId"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 md:w-72">
                    <option value="">Todas as disciplinas</option>
                    @foreach ($this->subjectOptions() as $subjectId => $subjectName)
                        <option value="{{ $subjectId }}">{{ $subjectName }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <x-filament::button wire:click="exportRows" icon="heroicon-o-arrow-down-tray">
            Exportar CSV
        </x-filament::button>
    </div>

    <div class="mb-4 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800">
        Mostra alunos matriculados em disciplinas com inscrição permitida que ainda não têm um turno atribuído.
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left">Número</th>
                    <th class="px-4 py-3 text-left">Aluno</th>
                    <th class="px-4 py-3 text-left">Turma</th>
                    <th class="px-4 py-3 text-left">Núcleo</th>
                    <th class="px-4 py-3 text-left">Disciplina</th>
                    <th class="px-4 py-3 text-left">E-mail</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $row)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-4 py-3">{{ $row->registration?->student?->number ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->registration?->student?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->registration?->class?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->registration?->class?->buildings?->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $row->subject?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->registration?->student?->email ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Não existem alunos nesta situação.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->rows->links() }}
    </div>
</x-filament::page>
