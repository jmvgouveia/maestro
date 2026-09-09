<x-filament::page>
    <div class="mb-6">
        {{ $this->form }}
    </div>

    @if ($this->merged)
    @php
    [
    'weekdays' => $weekdays,
    'timePeriods' => $timePeriods,
    'calendar' => $calendar,
    'teacherPalette' => $teacherPalette,
    'teachers' => $teachers,
    'recusados' => $recusados,
    'PedidosAprovadosDP' => $PedidosAprovadosDP,
    'escalados' => $escalados
    ] = $this->merged;
    @endphp

    {{-- Legenda de docentes (cores) --}}
    <div class="mb-4 flex flex-wrap gap-3 text-xs">
        @foreach ($teachers as $t)
        <span class="inline-flex items-center gap-2 px-2 py-1 rounded-md border"
            style="border-color: {{ $teacherPalette[$t->id] }};">
            <span class="inline-block h-3 w-3 rounded-full" style="background: {{ $teacherPalette[$t->id] }}"></span>
            {{ $t->name }}@if (!empty($teacherScopes[$t->id])) - {{ $teacherScopes[$t->id] }}@endif
        </span>
        @endforeach
    </div>

    @include('components.schedule-grid-merged', compact(
    'weekdays','timePeriods','calendar','teacherPalette',
    'recusados','PedidosAprovadosDP','escalados'
    ))
    @else
    <div class="text-sm text-gray-500">Seleciona um ou mais docentes para visualizar o horário sobreposto.</div>
    @endif
</x-filament::page>
