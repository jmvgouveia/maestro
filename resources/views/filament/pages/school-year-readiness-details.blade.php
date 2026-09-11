<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Consulta apenas de registos sem configuração no ano letivo
        {{ $this->activeSchoolYear()?->schoolyear ?? 'ativo' }}.
    </p>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-filament::section heading="Alunos sem matrícula">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="px-3 py-2">Nº</th>
                            <th class="px-3 py-2">Nome</th>
                            <th class="px-3 py-2">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->studentsWithoutRegistration() as $student)
                            <tr class="border-b last:border-0 dark:border-gray-800">
                                <td class="px-3 py-2">{{ $student->number }}</td>
                                <td class="px-3 py-2">{{ $student->name }}</td>
                                <td class="px-3 py-2">{{ $student->email ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-4 text-gray-500">Nenhum aluno encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Professores sem disciplinas">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="px-3 py-2">Nome</th>
                            <th class="px-3 py-2">Sigla</th>
                            <th class="px-3 py-2">Departamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->teachersWithoutSubject() as $teacher)
                            <tr class="border-b last:border-0 dark:border-gray-800">
                                <td class="px-3 py-2">{{ $teacher->name }}</td>
                                <td class="px-3 py-2">{{ $teacher->acronym ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $teacher->department?->name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-4 text-gray-500">Nenhum professor encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
