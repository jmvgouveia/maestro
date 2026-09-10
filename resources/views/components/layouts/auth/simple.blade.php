<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="public-auth-layout flex min-h-screen flex-col antialiased" style="background-color: #f8fafc;">
        <div class="flex min-h-svh flex-1 flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="public-auth-card flex w-full max-w-xl flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="mb-1 flex items-center justify-center">
                        <img
                            src="{{ asset('images/maestro-logo-light.svg') }}"
                            alt="Maestro"
                            class="h-40 w-auto object-contain dark:hidden"
                        />
                        <img
                            src="{{ asset('images/maestro-logo-dark.svg') }}"
                            alt="Maestro"
                            class="hidden h-40 w-auto object-contain dark:block"
                        />
                    </span>
                </a>
                <div class="mx-auto flex w-full max-w-lg flex-col gap-6">
                    {{ $slot }}
                </div>
                <div class="maestro-login-footer mt-5 text-center text-gray-400">
                    <p>Conservatório – Escola das Artes da Madeira, Eng. Luiz Peter Clode</p>
                    <p>© {{ now()->year }} · Versão: V.1</p>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
