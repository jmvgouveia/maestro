<div class="flex flex-col gap-6">
    <div class="public-auth-heading">
        <x-auth-header
            title="Ativar a sua conta"
            description="Defina a sua palavra-passe para concluir o primeiro acesso"
        />
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="activate" class="flex flex-col gap-6">
        <!-- Email Address -->
        <flux:input
            wire:model="email"
            label="Endereço de email"
            type="email"
            required
            autofocus
            autocomplete="email"
            placeholder="email@example.com"
        />

        <!-- Password -->
        <flux:input
            wire:model="password"
            label="Palavra-passe"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Palavra-passe"
            viewable
        />

        <!-- Confirm Password -->
        <flux:input
            wire:model="password_confirmation"
            label="Confirmar palavra-passe"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Confirmar palavra-passe"
            viewable
        />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="public-auth-button w-full">
                Ativar conta
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        Já tem uma conta?
        <flux:link :href="route('login')" wire:navigate>Iniciar sessão</flux:link>
    </div>
</div>
