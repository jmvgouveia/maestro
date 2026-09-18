<x-filament-panels::page>
    <div class="mx-auto w-full max-w-5xl space-y-6">
        @if (! auth()->user()->hasTwoFactorEnabled())
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:px-8">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/50 dark:text-primary-300">
                            <x-heroicon-o-shield-check class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">Segurança da conta</p>
                            <h2 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">Configure a autenticação multifator</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Proteja o acesso ao painel com uma aplicação autenticadora.</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="space-y-6">
                        <div>
                            <p class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">Como configurar</p>
                            <ol class="space-y-4">
                                <li class="flex gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white">1</span>
                                    <div class="pt-0.5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                        Instale o <strong class="font-semibold text-gray-900 dark:text-white">Microsoft Authenticator</strong> ou o Google Authenticator.
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white">2</span>
                                    <div class="pt-0.5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                        Na aplicação, escolha <strong class="font-semibold text-gray-900 dark:text-white">Adicionar conta</strong> e depois <strong class="font-semibold text-gray-900 dark:text-white">Digitalizar código QR</strong>.
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white">3</span>
                                    <div class="pt-0.5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                        Aponte a câmara da aplicação autenticadora para o código ao lado.
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white">4</span>
                                    <div class="pt-0.5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                        Introduza o código de 6 dígitos gerado e ative a autenticação.
                                    </div>
                                </li>
                            </ol>
                        </div>

                        <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-800/70 dark:bg-warning-950/25">
                            <div class="flex gap-3">
                                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-warning-600 dark:text-warning-400" />
                                <div class="text-sm leading-6 text-warning-900 dark:text-warning-100">
                                    <p class="font-semibold">Use a aplicação autenticadora</p>
                                    <p class="mt-1">A câmara normal do telemóvel apenas lê o código; não configura a conta. Abra primeiro o Microsoft Authenticator ou outra aplicação compatível.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-center rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-950/60">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Leia este código QR</p>
                        <p class="mt-1 text-center text-xs text-gray-500 dark:text-gray-400">Abra a aplicação autenticadora antes de digitalizar.</p>
                        @if ($qrCode)
                            <div class="my-5 flex w-full max-w-[17rem] items-center justify-center rounded-xl bg-white p-3 shadow-sm [&>svg]:h-auto [&>svg]:w-full">
                                {!! $qrCode !!}
                            </div>
                        @endif
                        <p class="text-center text-xs text-gray-500 dark:text-gray-400">Mantenha esta página aberta enquanto conclui a configuração.</p>
                    </div>
                </div>

                <div class="border-t border-gray-200 bg-gray-50/70 px-6 py-6 dark:border-gray-700 dark:bg-gray-950/30 sm:px-8">
                    <form wire:submit="confirm" class="mx-auto max-w-xl space-y-4">
                        <div>
                            <label for="mfa-code" class="block text-sm font-semibold text-gray-950 dark:text-white">Código de verificação</label>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Introduza o código atual mostrado na aplicação.</p>
                        </div>
                        <x-filament::input.wrapper :valid="! $errors->has('code')" class="[&>div]:!rounded-xl">
                            <x-filament::input id="mfa-code" wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" class="text-center text-lg tracking-[0.35em]" />
                        </x-filament::input.wrapper>
                        @error('code')
                            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                        <x-filament::button type="submit" class="w-full justify-center sm:w-auto">
                            Ativar autenticação multifator
                        </x-filament::button>
                    </form>
                </div>
            </div>
        @endif

        @if ($recoveryCodes)
            <x-filament::section>
                <x-slot name="heading">Códigos de recuperação</x-slot>
                <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 dark:border-danger-800/70 dark:bg-danger-950/25">
                    <p class="text-sm leading-6 text-danger-800 dark:text-danger-200">Guarde estes códigos num local seguro. Não serão novamente apresentados.</p>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-gray-50 p-4 font-mono text-sm dark:bg-gray-950 sm:grid-cols-3">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <span>{{ $recoveryCode }}</span>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if (auth()->user()->hasTwoFactorEnabled())
            <x-filament::section>
                <x-slot name="heading">Gerar novos códigos de recuperação</x-slot>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Confirme com o código da aplicação autenticadora. Não é possível usar um código de recuperação nesta ação.</p>
                <form wire:submit="regenerateRecoveryCodes" class="max-w-xl space-y-4">
                    <x-filament::input.wrapper :valid="! $errors->has('regenerationCode')">
                        <x-filament::input wire:model="regenerationCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="Código de 6 dígitos" />
                    </x-filament::input.wrapper>
                    @error('regenerationCode')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                    <x-filament::button type="submit" color="gray">Gerar novos códigos</x-filament::button>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
