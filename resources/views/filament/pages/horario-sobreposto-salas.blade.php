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
                'roomPalette' => $roomPalette,
                'rooms' => $rooms,
                'recusados' => $recusados,
                'PedidosAprovadosDP' => $PedidosAprovadosDP,
                'escalados' => $escalados,
                'roomScopes' => $roomScopes,
            ] = $this->merged;
        @endphp

        {{-- Legenda de salas (cores) --}}
        <div class="mb-4 flex flex-wrap gap-3 text-xs">
            @foreach ($rooms as $room)
                <span class="inline-flex items-center gap-2 px-2 py-1 rounded-md border"
                    style="border-color: {{ $roomPalette[$room->id] }};">
                    <span class="inline-block h-3 w-3 rounded-full" style="background: {{ $roomPalette[$room->id] }}"></span>
                    {{ $room->name }}@if (!empty($roomScopes[$room->id])) - {{ $roomScopes[$room->id] }}@endif
                </span>
            @endforeach
        </div>

        @include('components.schedule-grid-rooms', compact(
            'weekdays', 'timePeriods', 'calendar', 'roomPalette',
            'recusados', 'PedidosAprovadosDP', 'escalados'
        ))
    @else
        <div class="text-sm text-gray-500">Seleciona uma ou mais salas para visualizar o horário sobreposto.</div>
    @endif
</x-filament::page>
