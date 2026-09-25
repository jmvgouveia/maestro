<x-filament-panels::page>
    <div class="occupancy-page" wire:poll.30s="refreshOccupancy">
        <header class="occupancy-header">
            <div class="occupancy-heading">
                <p class="occupancy-eyebrow">Consulta em tempo real</p>
                <h2>Mapa de ocupação</h2>
            </div>
            <time
                class="occupancy-clock"
                x-data="{ now: new Date() }"
                x-init="setInterval(() => now = new Date(), 1000)"
                x-text="now.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit' })"
                aria-label="Hora atual"
            ></time>
        </header>

        <main class="occupancy-grid">
            @forelse ($this->rooms as $room)
                @php($occupied = $room->activeKeyControl !== null || $room->activeFloorKeyAccess !== null)
                <div class="occupancy-tile {{ $occupied ? 'is-occupied' : 'is-free' }}" aria-label="{{ $room->name }}: {{ $occupied ? 'ocupada' : 'livre' }}">
                    <span class="occupancy-features">
                        @if ($room->features->isNotEmpty())
                            @foreach ($room->features as $feature)
                                <span class="occupancy-feature-icon" aria-hidden="true">
                                    @if ($feature->icon_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($feature->icon_path) }}" alt="" />
                                    @elseif ($feature->icon)
                                        <x-dynamic-component :component="'heroicon-o-'.$feature->icon" />
                                    @else
                                        {{ $feature->symbol }}
                                    @endif
                                </span>
                            @endforeach
                        @else
                            <span class="occupancy-feature-icon occupancy-default-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <path d="M3.5 20.5h17M5.5 20.5v-9l6.5-5 6.5 5v9M9 20.5v-4h6v4M8 12h.01M12 12h.01M16 12h.01" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        @endif
                    </span>
                    <strong>{{ $room->name }}</strong>
                    <span class="occupancy-state"><i></i>{{ $occupied ? 'Ocupada' : 'Livre' }}</span>
                </div>
            @empty
                <div class="occupancy-empty">Não existem salas autorizadas para apresentar.</div>
            @endforelse
        </main>
    </div>

    <style>
        .occupancy-page{height:calc(100vh - 7rem);min-height:30rem;overflow:hidden}
        .occupancy-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.35rem;color:#fff;background:linear-gradient(120deg,#082f66,#0b4c9c);border-radius:1rem}
        .occupancy-eyebrow{margin:0;color:#bfdbfe;font-size:.7rem;font-weight:750;letter-spacing:.12em;text-transform:uppercase}
        .occupancy-header h2{margin:.2rem 0 0;font-size:1.55rem;font-weight:800}
        .occupancy-clock{font-variant-numeric:tabular-nums;font-size:clamp(1.25rem,2.2vw,2rem);font-weight:750;letter-spacing:.04em;white-space:nowrap}
        .occupancy-grid{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));grid-auto-rows:7.6rem;align-content:start;gap:.55rem;height:calc(100% - 5rem);margin-top:.7rem;overflow:hidden;padding-right:.15rem}
        .occupancy-tile{display:flex;min-width:0;min-height:0;flex-direction:column;align-items:center;justify-content:center;padding:.45rem;border:2px solid;border-radius:.75rem;text-align:center;transition:transform .15s ease}
        .occupancy-tile.is-free{color:#166534;background:#f0fdf4;border-color:#22c55e}
        .occupancy-tile.is-occupied{color:#991b1b;background:#fff1f2;border-color:#ef4444}
        .occupancy-tile strong{display:-webkit-box;width:100%;overflow:visible;font-size:clamp(.72rem,1vw,1rem);line-height:1.05;-webkit-box-orient:vertical;-webkit-line-clamp:2}
        .occupancy-features{display:flex;align-items:center;justify-content:center;gap:.2rem;margin-top:.28rem;min-height:2.1rem}
        .occupancy-feature-icon{display:grid;place-items:center;width:2.2rem;height:2.2rem;font-size:1.25rem;font-weight:700}
        .occupancy-feature-icon img,.occupancy-feature-icon svg{width:1.8rem;height:1.8rem;object-fit:contain}
        .occupancy-state{display:flex;align-items:center;gap:.3rem;margin-top:.35rem;padding:.22rem .6rem;border:1px solid;font-size:clamp(.58rem,.7vw,.72rem);font-weight:750;line-height:1;border-radius:999px}
        .occupancy-tile.is-free .occupancy-state{color:#15803d;background:#dcfce7;border-color:#bbf7d0}
        .occupancy-tile.is-occupied .occupancy-state{color:#b91c1c;background:#fee2e2;border-color:#fecaca}
        .occupancy-state i{display:inline-block;width:.5rem;height:.5rem;border-radius:999px}
        .occupancy-tile.is-free .occupancy-state i{background:#16a34a}
        .occupancy-tile.is-occupied .occupancy-state i{background:#dc2626}
        .occupancy-empty{grid-column:1/-1;padding:3rem;color:#64748b;text-align:center}
        .dark .occupancy-tile.is-free{color:#bbf7d0;background:#052e16}
        .dark .occupancy-tile.is-occupied{color:#fecaca;background:#450a0a}
        @media(max-width:1200px){.occupancy-grid{grid-template-columns:repeat(8,minmax(0,1fr));grid-auto-rows:6.8rem}}
        @media(max-width:900px){.occupancy-grid{grid-template-columns:repeat(6,minmax(0,1fr));grid-auto-rows:6.2rem}}
        @media(max-width:650px){.occupancy-page{height:calc(100vh - 5rem)}.occupancy-header{padding:.8rem 1rem}.occupancy-header h2{font-size:1.2rem}.occupancy-grid{grid-template-columns:repeat(4,minmax(0,1fr));grid-auto-rows:5.8rem;height:calc(100% - 4.4rem)}}
        @media(max-width:420px){.occupancy-grid{grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-rows:5.5rem}.occupancy-clock{font-size:1rem}.occupancy-eyebrow{font-size:.58rem}}
    </style>
</x-filament-panels::page>
