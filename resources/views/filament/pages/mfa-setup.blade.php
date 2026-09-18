<x-filament-panels::page>
    <div class="space-y-6">
        @if (! auth()->user()->hasTwoFactorEnabled())
            <x-filament::section>
                <x-slot name="heading">Configure a autenticação multifator</x-slot>

                <div class="mb-6 rounded-lg border border-primary-200 bg-primary-50 p-4 text-sm text-primary-900 dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-100">
                    <p class="mb-3 font-semibold">Siga estes passos:</p>
                    <ol class="list-decimal space-y-2 ps-5">
                        <li>Instale uma aplicação autenticadora no telemóvel, como o <strong>Microsoft Authenticator</strong> ou o Google Authenticator.</li>
                        <li>Abra a aplicação e escolha <strong>Adicionar conta</strong> ou o símbolo <strong>+</strong>.</li>
                        <li>Escolha <strong>Outra conta</strong>, <strong>Conta profissional ou escolar</strong> ou <strong>Digitalizar código QR</strong>, conforme a aplicação.</li>
                        <li>Aponte a câmara da aplicação autenticadora para o código QR apresentado abaixo.</li>
                        <li>Introduza aqui o código de 6 dígitos que a aplicação gerar e selecione <strong>Ativar autenticação multifator</strong>.</li>
                    </ol>
                </div>

                <div class="mb-6 rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-900 dark:border-warning-800 dark:bg-warning-950/30 dark:text-warning-100">
                    <p class="font-semibold">Importante</p>
                    <p class="mt-1">Não utilize a aplicação Câmara do telemóvel para ler este código. A câmara apenas reconhece o QR Code, mas não configura a autenticação. Tem de abrir primeiro o Microsoft Authenticator ou outra aplicação autenticadora e usar a opção <strong>Digitalizar código QR</strong> dentro dessa aplicação.</p>
                </div>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Mantenha esta página aberta enquanto configura a conta no telemóvel.
                </p>

                @if ($qrCode)
                    <div class="mb-6 flex justify-center rounded-lg bg-white p-4">{!! $qrCode !!}</div>
                @endif

                <form wire:submit="confirm" class="space-y-4">
                    <x-filament::input.wrapper :valid="! $errors->has('code')">
                        <x-filament::input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="Código de 6 dígitos" />
                    </x-filament::input.wrapper>
                    @error('code') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    <x-filament::button type="submit">Ativar autenticação multifator</x-filament::button>
                </form>
            </x-filament::section>
        @endif

        @if ($recoveryCodes)
            <x-filament::section>
                <x-slot name="heading">Códigos de recuperação</x-slot>

                <p class="mb-4 text-sm text-danger-600 dark:text-danger-400">Guarde estes códigos num local seguro. Não serão novamente apresentados.</p>
                <div class="grid grid-cols-2 gap-2 rounded-lg bg-gray-50 p-4 font-mono text-sm dark:bg-gray-900">
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
                <form wire:submit="regenerateRecoveryCodes" class="space-y-4">
                    <x-filament::input.wrapper :valid="! $errors->has('regenerationCode')">
                        <x-filament::input wire:model="regenerationCode" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="Código de 6 dígitos" />
                    </x-filament::input.wrapper>
                    @error('regenerationCode') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    <x-filament::button type="submit" color="gray">Gerar novos códigos</x-filament::button>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
