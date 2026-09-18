<x-filament::page>
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="grid flex-1 grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-5">
            <div>
                <label for="teacher-subject-shift-teacher" class="mb-1 block text-sm font-medium">Professor</label>
                <select id="teacher-subject-shift-teacher" wire:model.live="teacherFilterId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Todos os professores</option>
                    @foreach ($this->teacherOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="teacher-subject-shift-class" class="mb-1 block text-sm font-medium">Turma</label>
                <select id="teacher-subject-shift-class" wire:model.live="classFilterId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Todas as turmas</option>
                    @foreach ($this->classOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="teacher-subject-shift-subject" class="mb-1 block text-sm font-medium">Disciplina</label>
                <select id="teacher-subject-shift-subject" wire:model.live="subjectFilterId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Todas as disciplinas</option>
                    @foreach ($this->subjectOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="teacher-subject-shift-building" class="mb-1 block text-sm font-medium">Núcleo/Pólo</label>
                <select id="teacher-subject-shift-building" wire:model.live="buildingFilterId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Todos os núcleos</option>
                    @foreach ($this->buildingOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="teacher-subject-shift-shift" class="mb-1 block text-sm font-medium">Turno</label>
                <select id="teacher-subject-shift-shift" wire:model.live="shiftFilter" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Todos os turnos</option>
                    @foreach ($this->shiftOptions() as $shift)
                        <option value="{{ $shift }}">{{ $shift }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <x-filament::button wire:click="exportRows" icon="heroicon-o-arrow-down-tray">
            Exportar CSV
        </x-filament::button>
    </div>

    <div class="mb-4 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-primary-800">
        Consulta limitada ao ano letivo ativo e aos horários aprovados.
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left">Professor</th>
                    <th class="px-4 py-3 text-left">Disciplina</th>
                    <th class="px-4 py-3 text-left">Turma</th>
                    <th class="px-4 py-3 text-left">Núcleo/Pólo</th>
                    <th class="px-4 py-3 text-left">Turno</th>
                    <th class="px-4 py-3 text-left">Dia</th>
                    <th class="px-4 py-3 text-left">Hora</th>
                    <th class="px-4 py-3 text-left">Sala</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $row)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-4 py-3">{{ $row->teacher_name }}</td>
                        <td class="px-4 py-3">{{ $row->subject_name }}</td>
                        <td class="px-4 py-3">{{ $row->class_name }}</td>
                        <td class="px-4 py-3">{{ $row->building_name ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $row->shift ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $row->weekday_name }}</td>
                        <td class="px-4 py-3">{{ $row->timeperiod_description }}</td>
                        <td class="px-4 py-3">{{ $row->room_name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Não existem horários para os filtros selecionados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->rows->links() }}
    </div>
</x-filament::page>
