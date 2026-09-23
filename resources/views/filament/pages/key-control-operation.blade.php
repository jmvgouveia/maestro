<x-filament-panels::page>
    <div class="porter-page space-y-6">
        <div class="porter-hero">
            <div>
                <p class="porter-eyebrow">Controlo operacional</p>
                <h2>Salas e chaves</h2>
                <p>Consulte rapidamente o estado das salas autorizadas e registe cada movimento.</p>
            </div>
            <div class="porter-hero-tools">
                <div class="porter-clock" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)" aria-live="polite">
                    <time class="porter-clock-time" x-text="now.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit' })"></time>
                    <time class="porter-clock-date" x-text="now.toLocaleDateString('pt-PT', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })"></time>
                </div>
                <div class="porter-hero-mark" aria-hidden="true"><x-heroicon-o-key class="h-8 w-8" /></div>
            </div>
        </div>

        <div class="porter-stat-grid">
            <button type="button" wire:click="$set('selectedStatus', 'available')" class="porter-stat-card porter-stat-available {{ $selectedStatus === 'available' ? 'is-selected' : '' }}" aria-pressed="{{ $selectedStatus === 'available' ? 'true' : 'false' }}">
                <span class="porter-stat-icon"><x-heroicon-o-check-circle class="h-5 w-5" /></span><p>Salas livres</p><strong>{{ $this->availableRoomsCount }}</strong>
            </button>
            <button type="button" wire:click="$set('selectedStatus', 'occupied')" class="porter-stat-card porter-stat-occupied {{ $selectedStatus === 'occupied' ? 'is-selected' : '' }}" aria-pressed="{{ $selectedStatus === 'occupied' ? 'true' : 'false' }}">
                <span class="porter-stat-icon"><x-heroicon-o-lock-closed class="h-5 w-5" /></span><p>Salas ocupadas</p><strong>{{ $this->occupiedRoomsCount }}</strong>
            </button>
            <button type="button" wire:click="$set('selectedStatus', null)" class="porter-stat-card porter-stat-total {{ $selectedStatus === null ? 'is-selected' : '' }}" aria-pressed="{{ $selectedStatus === null ? 'true' : 'false' }}">
                <span class="porter-stat-icon"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span><p>Todas as salas</p><strong>{{ $this->authorizedRoomsCount }}</strong>
            </button>
        </div>

        <div class="porter-toolbar">
            <div class="porter-filters">
                <label for="room-search" class="porter-search">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5" aria-hidden="true" />
                    <span class="sr-only">Pesquisar sala</span>
                    <input id="room-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Pesquisar sala ou edifício" autocomplete="off">
                </label>
                <label for="building-filter" class="porter-filter-select">
                    <span class="sr-only">Filtrar por edifício</span>
                    <x-heroicon-o-building-office-2 class="h-5 w-5" aria-hidden="true" />
                    <select id="building-filter" wire:model.live="selectedBuildingId">
                        <option value="">Todos os edifícios</option>
                        @foreach ($this->buildings as $building)
                            <option value="{{ $building->id }}">{{ $building->address ?: $building->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="room-sort" class="porter-filter-select">
                    <span class="sr-only">Ordenar salas</span>
                    <x-heroicon-o-arrows-up-down class="h-5 w-5" aria-hidden="true" />
                    <select id="room-sort" wire:model.live="roomSort">
                        <option value="asc">Nome (A-Z)</option>
                        <option value="desc">Nome (Z-A)</option>
                    </select>
                </label>
            </div>
        </div>

        @if ($this->rooms->isEmpty())
            <div class="porter-empty"><x-heroicon-o-building-office-2 class="mx-auto h-10 w-10" /><p>Não tem salas autorizadas.</p></div>
        @else
            <div class="key-control-grid">
                @foreach ($this->rooms as $room)
                    @php($active = $this->activeKeyControlFor($room))
                    <article class="porter-room-card {{ $active ? 'is-occupied' : 'is-available' }}">
                        <div class="porter-room-heading">
                            <div><p class="porter-room-building">{{ $room->building?->name ?? 'Edifício não identificado' }}</p><h3>{{ $room->name }}</h3></div>
                            <span class="porter-status {{ $active ? 'status-occupied' : 'status-available' }}"><span aria-hidden="true"></span>{{ $active ? 'Ocupada' : 'Livre' }}</span>
                        </div>
                        <div class="porter-room-details">
                            @if ($active)
                                <div class="porter-active-info">
                                    <span class="porter-holder-avatar {{ $active->holderTypeLabel() === 'Aluno' ? 'is-student' : 'is-teacher' }}">
                                        @if ($active->holderTypeLabel() === 'Aluno')
                                            <x-heroicon-o-academic-cap class="h-6 w-6" aria-hidden="true" />
                                        @else
                                            <x-heroicon-o-user class="h-6 w-6" aria-hidden="true" />
                                        @endif
                                    </span>
                                    <div class="porter-active-copy">
                                        <div class="porter-holder-meta">
                                            <span class="porter-holder-badge {{ $active->holderTypeLabel() === 'Aluno' ? 'is-student' : 'is-teacher' }}">{{ mb_strtoupper($active->holderTypeLabel()) }}</span>
                                            <span class="porter-holder-number">{{ $active->holder?->number ?: 'Sem número' }}</span>
                                        </div>
                                        <strong class="porter-holder-name">{{ $active->holder?->name ?? 'Desconhecido' }}</strong>
                                        <p class="porter-date-value"><x-heroicon-o-calendar-days class="h-5 w-5" aria-hidden="true" /><span>{{ $active->picked_up_at->format('d/m/Y H:i') }}</span></p>
                                    </div>
                                </div>
                                @if ($active->pick_up_observations)<p class="porter-note">{{ $active->pick_up_observations }}</p>@endif
                            @else
                                <div class="porter-available-copy"><span class="porter-key-avatar"><x-heroicon-o-key class="h-7 w-7" /></span><strong>Chave disponível</strong></div>
                            @endif
                        </div>
                        <div class="porter-room-actions">
                            @if ($active)
                                <x-filament::button color="danger" icon="heroicon-o-arrow-uturn-left" size="sm" wire:click="selectReturn({{ $room->id }})" class="w-full">Devolver</x-filament::button>
                                <x-filament::button color="danger" icon="heroicon-o-pencil-square" outlined size="sm" wire:click="selectCorrect({{ $room->id }})" class="w-full">Corrigir registo</x-filament::button>
                            @else
                                <x-filament::button color="primary" icon="heroicon-o-key" size="sm" wire:click="selectPickUp({{ $room->id }})" class="w-full">Levantar</x-filament::button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <style>
        .key-control-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
        .porter-page { max-width: 96rem; margin-inline: auto; }
        .porter-hero { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding: 1.5rem 1.75rem; color: white; background: linear-gradient(120deg, #082f66, #0b4c9c); border-radius: 1rem; box-shadow: 0 10px 24px rgb(6 59 130 / 14%); }
        .porter-eyebrow { margin-bottom: .35rem; color: #bfdbfe; font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .porter-hero h2 { font-size: 1.65rem; font-weight: 750; letter-spacing: -.025em; }
        .porter-hero p:last-child { margin-top: .35rem; color: #dbeafe; font-size: .9rem; }
        .porter-hero-mark { display: grid; place-items: center; width: 3.5rem; height: 3.5rem; color: #082f66; background: #ffbf00; border-radius: 1rem; }
        .porter-hero-tools { display: flex; align-items: center; gap: 1.25rem; }
        .porter-clock { min-width: 10rem; text-align: right; }
        .porter-clock-time { display: block; color: white; font-size: 1.7rem; font-weight: 750; line-height: 1; letter-spacing: -.02em; }
        .porter-clock-date { display: block; margin-top: .4rem; color: #bfdbfe; font-size: .72rem; text-transform: capitalize; }
        .porter-stat-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
        .porter-stat-card { position: relative; display: block; width: 100%; min-height: 7.5rem; padding: 1.15rem 1.25rem; color: inherit; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: .85rem; box-shadow: 0 2px 5px rgb(15 23 42 / 4%); cursor: pointer; transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease; }
        .porter-stat-card:hover, .porter-stat-card:focus-visible { border-color: #94a3b8; box-shadow: 0 5px 14px rgb(15 23 42 / 9%); outline: none; }
        .porter-stat-card p { color: #64748b; font-size: .78rem; font-weight: 650; }
        .porter-stat-card strong { display: block; margin-top: .45rem; color: #172033; font-size: 2rem; line-height: 1; }
        .porter-stat-icon { position: absolute; top: 1rem; right: 1rem; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: .65rem; }
        .porter-stat-available .porter-stat-icon { color: #15803d; background: #dcfce7; }
        .porter-stat-occupied .porter-stat-icon { color: #b91c1c; background: #fee2e2; }
        .porter-stat-total .porter-stat-icon { color: #1d4ed8; background: #dbeafe; }
        .porter-stat-available.is-selected { border-color: #86efac; background: #f0fdf4; box-shadow: 0 0 0 2px rgb(22 163 74 / 12%); }
        .porter-stat-occupied.is-selected { border-color: #fca5a5; background: #fff7f7; box-shadow: 0 0 0 2px rgb(220 38 38 / 12%); }
        .porter-stat-total.is-selected { border-color: #93c5fd; background: #eff6ff; box-shadow: 0 0 0 2px rgb(37 99 235 / 12%); }
        .porter-toolbar { display: flex; align-items: end; gap: 1rem; padding: .25rem 0 .5rem; }
        .porter-toolbar h3 { color: #172033; font-size: 1.1rem; font-weight: 750; }
        .porter-toolbar p { margin-top: .2rem; color: #64748b; font-size: .8rem; }
        .porter-search { display: flex; align-items: center; gap: .6rem; width: auto; min-width: 0; min-height: 4.5rem; padding: .65rem .8rem; color: #64748b; background: white; border: 1px solid #dbe4f0; border-radius: .65rem; box-shadow: 0 1px 2px rgb(15 23 42 / 3%); }
        .porter-search input { width: 100%; color: #172033; background: transparent; border: 0; outline: 0; font-size: .85rem; }
        .porter-filters { display: grid; grid-template-columns: minmax(14rem, 1.6fr) repeat(2, minmax(10rem, 1fr)); align-items: center; gap: .65rem; width: 100%; }
        .porter-filter-select { display: flex; align-items: center; gap: .55rem; min-width: 0; min-height: 4.5rem; padding: .65rem .8rem; color: #64748b; background: white; border: 1px solid #dbe4f0; border-radius: .65rem; box-shadow: 0 1px 2px rgb(15 23 42 / 3%); }
        .porter-filter-select select { width: 100%; color: #172033; background: transparent; border: 0; outline: 0; font-size: .85rem; }
        .porter-room-card { display: flex; min-height: 14rem; flex-direction: column; padding: 1.15rem; background: white; border: 1px solid #e2e8f0; border-top: 3px solid #16a34a; border-radius: .85rem; box-shadow: 0 2px 5px rgb(15 23 42 / 4%); transition: box-shadow 150ms ease, transform 150ms ease; }
        .porter-room-card:hover { box-shadow: 0 10px 22px rgb(15 23 42 / 8%); transform: translateY(-1px); }
        .porter-room-card.is-occupied { background: #fffafa; border-top-color: #dc2626; }
        .porter-room-heading { display: flex; align-items: start; justify-content: space-between; gap: .75rem; }
        .porter-room-building { color: #64748b; font-size: .7rem; font-weight: 650; text-transform: uppercase; letter-spacing: .04em; }
        .porter-room-heading h3 { margin-top: .2rem; color: #172033; font-size: 1.1rem; font-weight: 750; }
        .porter-status { display: inline-flex; align-items: center; gap: .35rem; flex-shrink: 0; padding: .3rem .55rem; border-radius: 999px; font-size: .68rem; font-weight: 750; }
        .porter-status > span { width: .4rem; height: .4rem; border-radius: 999px; background: currentColor; }
        .status-available { color: #15803d; background: #dcfce7; }
        .status-occupied { color: #b91c1c; background: #fee2e2; }
        .porter-room-details { min-height: 4rem; margin: 1.35rem 0 .85rem; color: #475569; font-size: .8rem; }
        .porter-room-details p + p { margin-top: .45rem; }
        .porter-active-info { display: flex; align-items: center; gap: .7rem; }
        .porter-holder-avatar, .porter-key-avatar { display: grid; place-items: center; flex-shrink: 0; width: 3rem; height: 3rem; border-radius: 999px; }
        .porter-holder-avatar.is-student { color: #9333ea; background: #f3e8ff; }
        .porter-holder-avatar.is-teacher { color: #1d4ed8; background: #dbeafe; }
        .porter-field-label { display: block; margin: 0 0 .35rem; color: #94a3b8; font-size: .68rem; font-weight: 650; text-transform: uppercase; letter-spacing: .04em; }
        .porter-active-copy { min-width: 0; flex: 1; }
        .porter-holder-name { display: block; max-width: 100%; margin-top: .65rem; overflow: hidden; color: #172033; font-size: .95rem; font-weight: 750; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }
        .porter-holder-number { color: #64748b; font-size: .8rem; font-weight: 500; }
        .porter-date-value { display: flex !important; align-items: center; gap: .5rem; margin-top: .35rem !important; color: #475569; font-size: .8rem; font-weight: 500; }
        .porter-date-value > span { display: flex; flex-direction: column; gap: .15rem; }
        .porter-date-value strong { color: #94a3b8; font-size: .68rem; font-weight: 650; letter-spacing: .04em; text-transform: uppercase; }
        .porter-holder-badge { display: inline-flex; align-items: center; padding: .25rem .55rem; border-radius: 999px; font-size: .65rem; font-weight: 750; letter-spacing: .04em; }
        .porter-holder-badge.is-student { color: #c2410c; background: #ffedd5; }
        .porter-holder-badge.is-teacher { color: #1d4ed8; background: #dbeafe; }
        .porter-note { padding: .55rem .7rem; color: #475569; background: #f8fafc; border-radius: .45rem; }
        .porter-available-copy { display: flex; align-items: center; gap: .7rem; padding-top: 1rem; color: #15803d; font-weight: 650; }
        .porter-key-avatar { color: #15803d; background: #dcfce7; }
        .porter-room-actions { display: flex; flex-direction: column; gap: .4rem; margin-top: auto; }
        .porter-room-actions .fi-btn { min-height: 2.1rem; height: 2.1rem; padding-block: .2rem; white-space: nowrap; }
        .porter-empty { padding: 3rem 1rem; color: #64748b; text-align: center; background: white; border: 1px dashed #cbd5e1; border-radius: .85rem; }
        .porter-empty p { margin-top: .6rem; font-size: .9rem; }
        .dark .porter-stat-card, .dark .porter-room-card, .dark .porter-search, .dark .porter-filter-select, .dark .porter-empty { color: #e5e7eb; background: #111827; border-color: #334155; }
        .dark .porter-stat-card p, .dark .porter-toolbar p, .dark .porter-room-building, .dark .porter-date-value, .dark .porter-holder-number { color: #94a3b8; }
        .dark .porter-stat-card strong, .dark .porter-toolbar h3, .dark .porter-room-heading h3, .dark .porter-holder-name { color: #f8fafc; }
        .dark .porter-search input, .dark .porter-filter-select select { color: #f8fafc; }
        .dark .porter-room-card.is-occupied { background: #1f1518; border-top-color: #ef4444; }
        .dark .porter-stat-available.is-selected { background: #10251b; border-color: #22c55e; }
        .dark .porter-stat-occupied.is-selected { background: #2a171a; border-color: #ef4444; }
        .dark .porter-stat-total.is-selected { background: #12233d; border-color: #60a5fa; }
        .dark .status-available, .dark .porter-holder-badge.is-student { color: #86efac; background: #14532d; }
        .dark .status-occupied { color: #fca5a5; background: #7f1d1d; }
        .dark .porter-holder-badge.is-teacher { color: #bfdbfe; background: #1e3a8a; }
        .dark .porter-holder-avatar.is-student { color: #d8b4fe; background: #4c1d95; }
        .dark .porter-holder-avatar.is-teacher { color: #bfdbfe; background: #1e3a8a; }
        .dark .porter-available-copy { color: #86efac; }
        .dark .porter-note { color: #cbd5e1; background: #1e293b; }
        .dark .porter-key-avatar { color: #86efac; background: #14532d; }
        .key-control-form-surface { background: #f8fafc; }
        .key-control-operation-modal { border-radius: 1.5rem !important; }
        .dark .key-control-form-surface { background: #1f2937; }
        .dark .key-control-form-surface label { color: #f9fafb !important; }
        .dark .key-control-form-surface input, .dark .key-control-form-surface textarea, .dark .key-control-form-surface button { background-color: #111827 !important; border-color: #4b5563 !important; color: #f9fafb !important; }
        @media (max-width: 1280px) { .key-control-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 900px) { .key-control-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 900px) { .porter-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 700px) { .porter-hero { align-items: flex-start; padding: 1.2rem; } .porter-hero-tools { gap: .75rem; } .porter-clock { min-width: 0; } .porter-clock-time { font-size: 1.35rem; } .porter-clock-date { max-width: 8rem; font-size: .65rem; } .porter-hero-mark { display: none; } .porter-stat-grid { gap: .65rem; } .porter-stat-card { min-height: 6.5rem; padding: .9rem; } .porter-stat-card strong { font-size: 1.65rem; } .porter-stat-icon { top: .75rem; right: .75rem; width: 1.9rem; height: 1.9rem; } .porter-toolbar { align-items: stretch; flex-direction: column; } .porter-filters { grid-template-columns: 1fr; width: 100%; } .porter-filter-select, .porter-search { width: 100%; min-height: 3.5rem; } }
        @media (max-width: 640px) { .key-control-grid { grid-template-columns: minmax(0, 1fr); } }
    </style>

    @if ($mode && $selectedRoomId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4" wire:key="key-control-modal-{{ $mode }}-{{ $selectedRoomId }}" x-data x-on:keydown.escape.window="$wire.cancel()" role="dialog" aria-modal="true">
            <button type="button" class="absolute inset-0 h-full w-full cursor-default" wire:click="cancel" aria-label="Fechar"></button>
            <div class="key-control-operation-modal relative z-10 max-h-[90vh] w-full overflow-y-auto border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-900 sm:p-8" style="max-width: 42rem; width: calc(100% - 2rem);">
                <div class="mb-7 flex items-start justify-between gap-5 border-b border-gray-100 pb-6 dark:border-gray-800">
                    <div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/50 dark:text-primary-300"><x-heroicon-o-key class="h-6 w-6" /></div><div><p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">Controlo de Chaves</p><h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $mode === 'pickUp' ? 'Registar levantamento' : ($mode === 'return' ? 'Registar devolução' : 'Corrigir última operação') }}</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Confirme os dados antes de concluir.</p></div></div>
                    <div class="flex shrink-0 items-center gap-2"><span class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold text-white" style="background-color: #063b82;"><x-heroicon-o-building-office-2 class="h-4 w-4" />{{ $this->selectedRoomName }}</span><button type="button" wire:click="cancel" class="rounded-xl p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Fechar"><x-heroicon-o-x-mark class="h-6 w-6" /></button></div>
                </div>
                @if ($mode === 'pickUp')
                    <form wire:submit.prevent="submitPickUp" class="space-y-4"><div class="key-control-form-surface rounded-2xl p-6">{{ $this->pickUpForm }}</div><div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-800"><x-filament::button type="button" color="gray" wire:click="cancel" size="lg">Cancelar</x-filament::button><x-filament::button type="submit" color="primary" size="lg">Levantar chave</x-filament::button></div></form>
                @elseif ($mode === 'return')
                    <form wire:submit.prevent="submitReturn" class="space-y-4"><div class="key-control-form-surface rounded-2xl p-6">{{ $this->returnForm }}</div><div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-800"><x-filament::button type="button" color="gray" wire:click="cancel" size="lg">Cancelar</x-filament::button><x-filament::button type="submit" color="success" size="lg">Registar devolução</x-filament::button></div></form>
                @else
                    <form wire:submit.prevent="submitCorrect" class="space-y-4"><div class="key-control-form-surface rounded-2xl p-6">{{ $this->correctForm }}</div><div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-800"><x-filament::button type="button" color="gray" wire:click="cancel" size="lg">Cancelar</x-filament::button><x-filament::button type="submit" color="warning" size="lg">Guardar correção</x-filament::button></div></form>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
