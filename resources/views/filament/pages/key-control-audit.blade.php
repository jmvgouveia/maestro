<x-filament-panels::page>
    <div class="audit-page">
        <section class="audit-hero">
            <div>
                <p class="audit-eyebrow">Rastreabilidade operacional</p>
                <h2>Histórico de chaves</h2>
                <p>Consulte a sequência completa de movimentos, sem perder nenhum registo.</p>
            </div>
            <div class="audit-hero-mark"><x-heroicon-o-clipboard-document-list class="h-8 w-8" /></div>
        </section>

        <div class="audit-stats">
            <div class="audit-stat"><span class="audit-stat-icon audit-blue"><x-heroicon-o-list-bullet class="h-5 w-5" /></span><span>Eventos encontrados</span><strong>{{ $this->filteredEvents()->count() }}</strong></div>
            <div class="audit-stat"><span class="audit-stat-icon audit-amber"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span><span>Salas com movimentos</span><strong>{{ $this->groupedEvents->count() }}</strong></div>
            <div class="audit-stat"><span class="audit-stat-icon audit-red"><x-heroicon-o-exclamation-triangle class="h-5 w-5" /></span><span>Chaves não devolvidas</span><strong>{{ $this->filteredEvents()->where('event_type', \App\Models\KeyControlEvent::ROOM_RELEASED)->count() }}</strong></div>
        </div>

        <section class="audit-filters">
            <div class="audit-filter-heading"><div><p class="audit-eyebrow">Pesquisa</p><h3>Filtrar histórico</h3></div><x-heroicon-o-funnel class="h-5 w-5 text-slate-400" /></div>
            <div class="audit-filter-grid">
                <label>Data inicial<input type="date" wire:model.live="dateFrom"></label>
                <label>Data final<input type="date" wire:model.live="dateUntil"></label>
                <label>Operação<select wire:model.live="eventType"><option value="">Todas as operações</option>@foreach (\App\Filament\Pages\KeyControlAudit::eventOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label>Sala<select wire:model.live="roomId"><option value="">Todas as salas</option>@foreach ($this->rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select></label>
                <label class="audit-person-filter">Professor / aluno<select wire:model.live="person"><option value="">Todos</option>@foreach ($this->people as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <div class="audit-filter-actions"><button type="button" wire:click="resetFilters" class="audit-button audit-button-light">Limpar</button><button type="button" wire:click="exportCsv" class="audit-button audit-button-primary"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Exportar CSV</button></div>
            </div>
        </section>

        <section class="audit-results">
            <div class="audit-results-heading"><div><p class="audit-eyebrow">Linha temporal</p><h3>Movimentos por sala e pessoa</h3></div><span>{{ $this->filteredEvents()->count() }} registos</span></div>
            @forelse ($this->groupedEvents as $roomEvents)
                @php($room = $roomEvents->first()->first()->keyControl?->room)
                <details class="audit-room" open>
                    <summary><span class="audit-summary-main"><span class="audit-room-icon"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span><span><strong>{{ $room?->name ?? 'Sala desconhecida' }}</strong><small>{{ $roomEvents->count() }} pessoa(s) · {{ $roomEvents->flatten(1)->count() }} eventos</small></span></span><x-heroicon-o-chevron-down class="audit-chevron h-5 w-5" /></summary>
                    <div class="audit-room-body">
                        @foreach ($roomEvents as $personEvents)
                            @php($eventPerson = $personEvents->first())
                            <details class="audit-person" open>
                                <summary><span class="audit-person-main"><span class="audit-avatar"><x-heroicon-o-user class="h-5 w-5" /></span><span><strong>{{ $this->personName($eventPerson) }}</strong><small>{{ $personEvents->count() }} evento(s)</small></span></span><x-heroicon-o-chevron-down class="audit-chevron h-4 w-4" /></summary>
                                <div class="audit-timeline">
                                    @foreach ($personEvents as $event)
                                        <div class="audit-event">
                                            <span class="audit-event-dot event-{{ \App\Filament\Pages\KeyControlAudit::eventColor($event->event_type) }}"></span><time>{{ $event->occurred_at?->format('d/m/Y H:i') }}</time><strong>{{ \App\Filament\Pages\KeyControlAudit::eventLabel($event->event_type, $event->data) }}</strong><span class="audit-event-user">{{ $event->performedBy?->name ?? 'Automático' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </details>
            @empty
                <div class="audit-empty"><x-heroicon-o-clipboard-document-list class="mx-auto h-10 w-10" /><strong>Sem movimentos</strong><span>Não existem registos para os filtros selecionados.</span></div>
            @endforelse
        </section>
    </div>

    <style>
        .audit-page{max-width:96rem;margin-inline:auto}.audit-hero{display:flex;align-items:center;justify-content:space-between;gap:1.5rem;padding:1.5rem 1.75rem;color:#fff;background:linear-gradient(120deg,#082f66,#0b4c9c);border-radius:1rem;box-shadow:0 10px 24px rgb(6 59 130 / 14%)}.audit-eyebrow{margin-bottom:.35rem;color:#2563eb;font-size:.68rem;font-weight:750;letter-spacing:.12em;text-transform:uppercase}.audit-hero .audit-eyebrow{color:#bfdbfe}.audit-hero h2{font-size:1.65rem;font-weight:750;letter-spacing:-.025em}.audit-hero p:last-child{margin-top:.35rem;color:#dbeafe;font-size:.9rem}.audit-hero-mark{display:grid;place-items:center;width:3.5rem;height:3.5rem;color:#082f66;background:#ffbf00;border-radius:1rem}.audit-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-top:1rem}.audit-stat{position:relative;min-height:6.5rem;padding:1rem 1.15rem;background:#fff;border:1px solid #e5e7eb;border-radius:.85rem;box-shadow:0 2px 5px rgb(15 23 42 / 4%)}.audit-stat span:not(.audit-stat-icon){display:block;color:#64748b;font-size:.78rem;font-weight:650}.audit-stat strong{display:block;margin-top:.4rem;color:#172033;font-size:1.9rem;line-height:1}.audit-stat-icon{position:absolute;top:1rem;right:1rem;display:grid;place-items:center;width:2.2rem;height:2.2rem;border-radius:.65rem}.audit-blue{color:#1d4ed8;background:#dbeafe}.audit-amber{color:#b45309;background:#fef3c7}.audit-red{color:#b91c1c;background:#fee2e2}.audit-filters,.audit-results{margin-top:1rem;padding:1.2rem;background:#fff;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 2px 5px rgb(15 23 42 / 3%)}.audit-filter-heading,.audit-results-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}.audit-filter-heading h3,.audit-results-heading h3{color:#172033;font-size:1rem;font-weight:750}.audit-filter-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.7rem;align-items:end}.audit-filter-grid label{color:#475569;font-size:.72rem;font-weight:650}.audit-filter-grid input,.audit-filter-grid select{display:block;width:100%;height:2.65rem;margin-top:.35rem;padding:0 .7rem;color:#172033;background:#fff;border:1px solid #cbd5e1;border-radius:.55rem;font-size:.8rem}.audit-filter-actions{display:flex;align-items:end;gap:.5rem;height:100%}.audit-button{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;height:2.65rem;padding:0 .8rem;border-radius:.55rem;font-size:.75rem;font-weight:700;white-space:nowrap}.audit-button-light{color:#475569;background:#f8fafc;border:1px solid #cbd5e1}.audit-button-primary{color:#fff;background:#063b82}.audit-results-heading>span{color:#64748b;font-size:.78rem}.audit-room{overflow:hidden;margin-top:.7rem;border:1px solid #dbe4f0;border-radius:.75rem}.audit-room>summary,.audit-person>summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;list-style:none;cursor:pointer}.audit-room>summary::-webkit-details-marker,.audit-person>summary::-webkit-details-marker{display:none}.audit-room>summary{padding:.85rem 1rem;background:#f8fafc}.audit-room[open]>summary{border-bottom:1px solid #dbe4f0}.audit-summary-main,.audit-person-main{display:flex;align-items:center;gap:.7rem}.audit-room-icon{display:grid;place-items:center;width:2.3rem;height:2.3rem;color:#1d4ed8;background:#dbeafe;border-radius:.6rem}.audit-summary-main strong,.audit-person-main strong{display:block;color:#172033;font-size:.9rem}.audit-summary-main small,.audit-person-main small{display:block;margin-top:.15rem;color:#64748b;font-size:.72rem}.audit-chevron{color:#64748b;transition:transform 150ms ease}.audit-room[open]>summary .audit-chevron,.audit-person[open]>summary .audit-chevron{transform:rotate(180deg)}.audit-room-body{padding:.65rem}.audit-person{overflow:hidden;margin:.45rem 0;border:1px solid #e5e7eb;border-radius:.6rem}.audit-person>summary{padding:.7rem .8rem;background:#fff}.audit-avatar{display:grid;place-items:center;width:2rem;height:2rem;color:#1d4ed8;background:#dbeafe;border-radius:999px}.audit-timeline{padding:.2rem .8rem .45rem;border-top:1px solid #f1f5f9}.audit-event{display:grid;grid-template-columns:.6rem 9.5rem 1fr 12rem;align-items:center;gap:.7rem;min-height:2.7rem;border-bottom:1px solid #f1f5f9;color:#475569;font-size:.78rem}.audit-event:last-child{border-bottom:0}.audit-event time{color:#64748b}.audit-event strong{color:#172033}.audit-event-user{color:#64748b;text-align:right}.audit-event-dot{width:.55rem;height:.55rem;border-radius:999px}.event-danger{background:#dc2626}.event-success{background:#16a34a}.event-warning{background:#d97706}.event-info{background:#2563eb}.audit-empty{padding:3rem 1rem;color:#64748b;text-align:center}.audit-empty strong,.audit-empty span{display:block;margin-top:.5rem}.dark .audit-stat,.dark .audit-filters,.dark .audit-results,.dark .audit-person>summary{color:#e5e7eb;background:#111827;border-color:#334155}.dark .audit-stat strong,.dark .audit-filter-heading h3,.dark .audit-results-heading h3,.dark .audit-summary-main strong,.dark .audit-person-main strong,.dark .audit-event strong{color:#f8fafc}.dark .audit-filter-grid input,.dark .audit-filter-grid select{color:#f8fafc;background:#1f2937;border-color:#4b5563}.dark .audit-room>summary{background:#1f2937;border-color:#334155}.dark .audit-person,.dark .audit-timeline,.dark .audit-event{border-color:#334155}.dark .audit-room-icon,.dark .audit-avatar{color:#bfdbfe;background:#1e3a8a}@media(max-width:1000px){.audit-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.audit-event{grid-template-columns:.6rem 8rem 1fr}}@media(max-width:650px){.audit-stats{grid-template-columns:1fr}.audit-filter-grid{grid-template-columns:1fr}.audit-filter-actions{align-items:stretch}.audit-event{grid-template-columns:.6rem 1fr;gap:.35rem;padding:.6rem 0}.audit-event strong,.audit-event-user{grid-column:2;text-align:left}.audit-hero{padding:1.2rem}.audit-hero-mark{display:none}}
    </style>
</x-filament-panels::page>
