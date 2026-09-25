<x-filament-panels::page>
    <div class="pending-returns-page">
        <div class="pending-returns-grid">
            @forelse ($this->pendingKeys as $key)
                @php($floorAccess = $key->room?->activeFloorKeyAccess)
                <article class="pending-return-card" wire:click="selectReturn({{ $key->getKey() }})" role="button" tabindex="0">
                    <div class="pending-return-heading">
                        <div><p class="pending-return-building">{{ $key->room?->building?->name }}</p><h2>{{ $key->room?->name }}</h2></div>
                    </div>
                    <div class="pending-return-person">
                        <span class="pending-return-avatar"><x-heroicon-o-user class="h-6 w-6" /></span>
                        <div><div class="pending-return-holder-meta"><span class="pending-return-holder-badge">{{ mb_strtoupper($key->holderTypeLabel()) }}</span><span>{{ $key->holder?->number ?: 'Sem número' }}</span></div><strong>{{ $key->holderDisplayName() }}</strong><p>Levantada em {{ $key->picked_up_at?->format('d/m/Y H:i') }}</p></div>
                    </div>
                </article>
            @empty
                <div class="pending-return-empty">Não existem chaves pendentes.</div>
            @endforelse
        </div>
    </div>

    <style>
        .pending-returns-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
        .pending-return-card { display:flex; min-height:10.75rem; flex-direction:column; padding:.95rem 1rem; background:#fff; border:1px solid #e2e8f0; border-top:3px solid #dc2626; border-radius:.85rem; box-shadow:0 2px 5px rgb(15 23 42 / 4%); cursor:pointer; transition:box-shadow 150ms ease,transform 150ms ease; }
        .pending-return-card:hover,.pending-return-card:focus-visible { box-shadow:0 10px 22px rgb(15 23 42 / 8%); transform:translateY(-1px); outline:3px solid #ffbf00; outline-offset:2px; }
        .pending-return-heading { display:flex; align-items:start; justify-content:space-between; gap:.75rem; }
        .pending-return-building { color:#64748b; font-size:.7rem; font-weight:650; text-transform:uppercase; letter-spacing:.04em; }
        .pending-return-heading h2 { margin-top:.2rem; color:#172033; font-size:1.1rem; font-weight:750; }
        .pending-return-status { padding:.3rem .55rem; color:#b91c1c; background:#fee2e2; border-radius:999px; font-size:.68rem; font-weight:750; }.pending-return-status.is-available { color:#15803d; background:#dcfce7; }
        .pending-return-person { display:flex; align-items:center; gap:.7rem; min-height:3.75rem; margin:1rem 0 .65rem; }
        .pending-return-avatar { display:grid; place-items:center; flex-shrink:0; width:3rem; height:3rem; color:#1d4ed8; background:#dbeafe; border-radius:999px; }
        .pending-return-person strong { display:block; margin-top:.35rem; color:#172033; font-size:.95rem; }.pending-return-holder-meta { display:flex; align-items:center; gap:.4rem; color:#64748b; font-size:.8rem; }.pending-return-holder-badge { padding:.25rem .55rem; color:#1d4ed8; background:#dbeafe; border-radius:999px; font-size:.65rem; font-weight:750; }
        .pending-return-person p,.pending-return-note { margin-top:.35rem; color:#475569; font-size:.8rem; }
        .pending-return-note { margin-top:auto; }
        .pending-return-empty { grid-column:1/-1; padding:3rem 1rem; color:#64748b; text-align:center; border:1px dashed #cbd5e1; border-radius:.85rem; }
        .dark .pending-return-card { background:#111827; border-color:#334155; }.dark .pending-return-heading h2,.dark .pending-return-person strong { color:#f8fafc; }.dark .pending-return-person p,.dark .pending-return-note { color:#94a3b8; }
        @media (max-width:1280px) { .pending-returns-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media (max-width:900px) { .pending-returns-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:640px) { .pending-returns-grid { grid-template-columns:1fr; } }
    </style>

    @if ($selectedKeyControlId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4" wire:key="pending-return-modal-{{ $selectedKeyControlId }}" x-data x-on:keydown.escape.window="$wire.cancel()" role="dialog" aria-modal="true">
            <button type="button" class="absolute inset-0 h-full w-full cursor-default" wire:click="cancel" aria-label="Fechar"></button>
            <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-900 sm:p-8">
                <div class="mb-6 flex items-start justify-between border-b border-gray-100 pb-5 dark:border-gray-800"><div><p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Controlo de chaves</p><h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">Devolver chave</h2></div><button type="button" wire:click="cancel" class="rounded-xl p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Fechar"><x-heroicon-o-x-mark class="h-6 w-6" /></button></div>
                <form wire:submit.prevent="submitReturn" class="space-y-4"><div class="rounded-2xl bg-gray-50 p-6 dark:bg-gray-800">{{ $this->returnForm }}</div><div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-800"><x-filament::button type="button" color="gray" wire:click="cancel" size="lg">Cancelar</x-filament::button><x-filament::button type="submit" color="success" size="lg">Registar devolução</x-filament::button></div></form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
