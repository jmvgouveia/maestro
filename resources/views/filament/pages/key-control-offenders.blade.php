<x-filament-panels::page>
    <div class="offenders-page">
        <section class="offenders-hero">
            <div>
                <p class="offenders-eyebrow">Consulta de gestão</p>
                <h2>Alunos com mais ocorrências</h2>
                <p>Cada ocorrência começa quando o aluno ultrapassa o limite configurado, por defeito duas horas.</p>
            </div>
            <div class="offenders-hero-mark"><x-heroicon-o-exclamation-triangle class="h-8 w-8" /></div>
        </section>

        <div class="offenders-stats">
            <div><span>Alunos encontrados</span><strong>{{ $this->offenders->count() }}</strong></div>
            <div><span>Total de ocorrências</span><strong>{{ $this->totalIncidents }}</strong></div>
            <div><span>Atraso acumulado</span><strong>{{ intdiv($this->totalDelayMinutes, 60) }}h {{ $this->totalDelayMinutes % 60 }}m</strong></div>
        </div>

        <section class="offenders-filters">
            <div class="offenders-filter-grid">
                <label>Levantamento desde<input type="date" wire:model.live="dateFrom"></label>
                <label>Data final<input type="date" wire:model.live="dateUntil"></label>
                <label>Sala<select wire:model.live="roomId"><option value="">Todas as salas</option>@foreach ($this->rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select></label>
                <div class="offenders-actions"><button type="button" wire:click="resetFilters" class="offenders-button offenders-button-light">Limpar</button>@if (auth()->user()?->can('export key control'))<button type="button" wire:click="exportCsv" class="offenders-button offenders-button-primary"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Exportar CSV</button>@endif</div>
            </div>
        </section>

        <section class="offenders-results">
            <div class="offenders-heading"><div><h3>Top 5 alunos</h3><span>{{ min(5, $this->offenders->count()) }} de {{ $this->offenders->count() }} alunos</span></div><label class="offenders-ranking-order">Ordenar por<select wire:model.live="rankingOrder"><option value="occurrences">Mais ocorrências</option><option value="delay">Maior atraso acumulado</option></select></label></div>
            <div class="offenders-table-wrap">
                <table class="offenders-table">
                    <thead><tr><th>Pos.</th><th>Aluno</th><th>Número</th><th>Ocorrências</th><th>Situação</th></tr></thead>
                    <tbody>
                        @forelse ($this->offenders->take(5) as $position => $offender)
                            <tr><td>{{ $position + 1 }}</td><td class="offender-name"><a href="{{ \App\Filament\Pages\KeyControlAudit::getUrl(['person' => \App\Models\Student::class.':'.$offender->id]) }}">{{ $offender->name }}</a></td><td>{{ $offender->number ?: '-' }}</td><td><strong class="offender-count">{{ $offender->occurrences_count }}</strong></td><td><span class="offender-status {{ $offender->pending_occurrences > 0 ? 'offender-status-pending' : 'offender-status-ok' }}">{{ $offender->pending_occurrences > 0 ? $offender->pending_occurrences.' pendente(s)' : 'Regularizado' }}</span></td></tr>
                        @empty
                            <tr><td colspan="5" class="offenders-empty">Não existem ocorrências para os filtros selecionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="offenders-results">
            <div class="offenders-heading"><div><h3>Detalhe das ocorrências</h3><span>{{ $this->occurrenceRows->count() }} registos</span></div><label class="offenders-search">Pesquisar aluno<input type="search" wire:model.live.debounce.300ms="search" placeholder="Nome ou número"></label></div>
            <div class="offenders-table-wrap">
                <table class="offenders-table">
                    <thead><tr><th>Aluno</th><th>Número</th><th>Recebeu a chave</th><th>Alerta</th><th>Entregou a chave</th><th>Atraso após alerta</th></tr></thead>
                    <tbody>
                        @forelse ($this->visibleOccurrenceRows as $occurrence)
                            <tr><td class="offender-name"><a href="{{ \App\Filament\Pages\KeyControlAudit::getUrl(['person' => \App\Models\Student::class.':'.$occurrence->student_id]) }}">{{ $occurrence->name }}</a></td><td>{{ $occurrence->number ?: '-' }}</td><td>{{ \Carbon\Carbon::parse($occurrence->picked_up_at)->format('d/m/Y H:i') }}</td><td>{{ \Carbon\Carbon::parse($occurrence->alert_at)->format('d/m/Y H:i') }}</td><td>{{ $occurrence->returned_at ? \Carbon\Carbon::parse($occurrence->returned_at)->format('d/m/Y H:i') : 'Pendente' }}</td><td><strong class="offender-count">{{ intdiv((int) $occurrence->delay_minutes, 60) }}h {{ (int) $occurrence->delay_minutes % 60 }}m</strong></td></tr>
                        @empty
                            <tr><td colspan="6" class="offenders-empty">Não existem ocorrências para os filtros selecionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="offenders-pagination"><span>{{ $this->occurrenceRows->count() ? (($this->detailPage - 1) * $this->perPage + 1) : 0 }}-{{ min($this->detailPage * $this->perPage, $this->occurrenceRows->count()) }} de {{ $this->occurrenceRows->count() }}</span><div class="offenders-pagination-actions"><label class="offenders-per-page">Mostrar<select wire:model.live="perPage"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label>@if ($this->detailPageCount > 1)<button type="button" wire:click="previousDetailPage" @disabled($this->detailPage === 1)>Anterior</button><button type="button" wire:click="nextDetailPage" @disabled($this->detailPage === $this->detailPageCount)>Seguinte</button>@endif</div></div>
        </section>
    </div>
    <style>
        .offenders-page{max-width:96rem;margin-inline:auto}.offenders-hero{display:flex;align-items:center;justify-content:space-between;gap:1.5rem;padding:1.5rem 1.75rem;color:#fff;background:linear-gradient(120deg,#082f66,#0b4c9c);border-radius:1rem;box-shadow:0 10px 24px rgb(6 59 130 / 14%)}.offenders-eyebrow{margin-bottom:.35rem;color:#bfdbfe;font-size:.68rem;font-weight:750;letter-spacing:.12em;text-transform:uppercase}.offenders-hero h2{font-size:1.65rem;font-weight:750}.offenders-hero p:last-child{margin-top:.35rem;color:#dbeafe;font-size:.9rem}.offenders-hero-mark{display:grid;place-items:center;width:3.5rem;height:3.5rem;color:#082f66;background:#ffbf00;border-radius:1rem}.offenders-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1rem}.offenders-stats>div,.offenders-filters,.offenders-results{padding:1.15rem;background:#fff;border:1px solid #e2e8f0;border-radius:.85rem;box-shadow:0 2px 5px rgb(15 23 42 / 4%)}.offenders-stats span{display:block;color:#64748b;font-size:.78rem;font-weight:650}.offenders-stats strong{display:block;margin-top:.4rem;color:#172033;font-size:1.9rem}.offenders-filters,.offenders-results{margin-top:1rem}.offenders-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;align-items:end}.offenders-filter-grid label{color:#475569;font-size:.78rem}.offenders-filter-grid input,.offenders-filter-grid select,.offenders-per-page select,.offenders-ranking-order select{display:block;width:100%;margin-top:.35rem;padding:.6rem .7rem;color:#172033;background:#fff;border:1px solid #cbd5e1;border-radius:.5rem}.offenders-actions{display:flex;gap:.5rem}.offenders-button{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.6rem .8rem;border-radius:.5rem;font-size:.78rem;font-weight:700}.offenders-button-light{color:#475569;background:#f1f5f9}.offenders-button-primary{color:#fff;background:#063b82}.offenders-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}.offenders-heading h3{color:#172033;font-size:1rem;font-weight:750}.offenders-heading span{color:#64748b;font-size:.78rem}.offenders-per-page,.offenders-ranking-order{display:flex;align-items:center;gap:.5rem;color:#64748b;font-size:.78rem}.offenders-per-page select,.offenders-ranking-order select{width:auto;margin-top:0}.offenders-table-wrap{overflow-x:auto}.offenders-table{width:100%;border-collapse:collapse;color:#475569;font-size:.82rem}.offenders-table th{padding:.7rem;text-align:left;color:#64748b;background:#f8fafc;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em}.offenders-table td{padding:.8rem .7rem;border-top:1px solid #e2e8f0}.offender-name{color:#172033;font-weight:700}.offender-count,.offender-status{display:inline-flex;min-width:1.8rem;justify-content:center;padding:.25rem .45rem;border-radius:999px}.offender-count{color:#991b1b;background:#fee2e2}.offender-status{font-size:.72rem;font-weight:700}.offender-status-pending{color:#92400e;background:#fef3c7}.offender-status-ok{color:#166534;background:#dcfce7}.offenders-pagination{display:flex;align-items:center;justify-content:space-between;margin-top:1rem;color:#64748b;font-size:.78rem}.offenders-pagination div{display:flex;gap:.5rem}.offenders-pagination button{padding:.45rem .7rem;color:#334155;background:#f1f5f9;border-radius:.45rem}.offenders-pagination button:disabled{cursor:not-allowed;opacity:.45}.dark .offenders-stats>div,.dark .offenders-filters,.dark .offenders-results{color:#e5e7eb;background:#111827;border-color:#334155}.dark .offenders-stats strong,.dark .offenders-heading h3,.dark .offender-name{color:#f8fafc}.dark .offenders-filter-grid input,.dark .offenders-filter-grid select,.dark .offenders-per-page select,.dark .offenders-ranking-order select{color:#f8fafc;background:#1f2937;border-color:#475569}.dark .offenders-table th{background:#1f2937}.dark .offenders-table td{border-color:#334155}@media(max-width:900px){.offenders-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.offenders-hero{padding:1.15rem}.offenders-hero-mark{display:none}.offenders-stats{grid-template-columns:1fr}.offenders-filter-grid{grid-template-columns:1fr}.offenders-actions{justify-content:flex-end}}
    </style>
    <style>
        .offenders-search{display:flex;align-items:center;gap:.55rem;color:#64748b;font-size:.78rem}.offenders-search input{width:15rem;margin-top:0;padding:.6rem .7rem;color:#172033;background:#fff;border:1px solid #cbd5e1;border-radius:.5rem}.offenders-pagination-actions{display:flex;align-items:center;gap:.5rem}.offender-name a{color:inherit;text-decoration:none}.offender-name a:hover{color:#2563eb;text-decoration:underline}.dark .offenders-search input{color:#f8fafc;background:#1f2937;border-color:#475569}@media(max-width:600px){.offenders-heading{align-items:flex-start;gap:.75rem;flex-direction:column}.offenders-search{width:100%;justify-content:space-between}.offenders-search input{width:12rem}.offenders-pagination{align-items:flex-start;gap:.75rem;flex-direction:column}.offenders-pagination-actions{width:100%;justify-content:flex-end}}
    </style>
</x-filament-panels::page>
