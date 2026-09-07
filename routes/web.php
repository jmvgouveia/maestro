<?php

use App\Http\Middleware\EnforceMfa;
use App\Http\Middleware\EnforceReadOnlyImpersonation;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Lab404\Impersonate\Services\ImpersonateManager;
use Illuminate\Http\Request;


Route::get('/', function () {
    return redirect('/maestro');
})->name('home');

Route::view('/privacidade', 'public.privacy')->name('privacy');
Route::view('/seguranca', 'public.security')->name('security');
Route::view('/suporte', 'public.support')->name('support');

Route::get('/filament-impersonate/leave', fn () => abort(405));

Route::post('/filament-impersonate/leave', function (ImpersonateManager $impersonation): RedirectResponse {
    abort_unless($impersonation->isImpersonating(), 404);

    $redirect = session()->pull('impersonate.back_to', '/maestro');
    abort_unless($impersonation->leave(), 403);

    $redirect = str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')
        ? $redirect
        : '/maestro';

    return new RedirectResponse($redirect);
})->middleware(['auth', EnforceMfa::class, EnforceReadOnlyImpersonation::class])
    ->name('impersonation.leave');

Route::middleware([EnforceMfa::class, EnforceReadOnlyImpersonation::class])->group(function (): void {
    Route::get('/meuhorario', function () {
        if (! Auth::check()) {
            return redirect()->guest('/maestro/login');
        }

        abort_unless(Auth::user()->hasRole('Professor'), 403);

        return redirect('/maestro/o-meu-horario');
    })->name('teacher.schedule.shortcut');

    Route::redirect('dashboard', 'maestro')
        ->middleware(['auth', 'verified', AuthenticateSession::class])
        ->name('dashboard');

    Route::middleware(['auth', AuthenticateSession::class])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Route::get('settings/profile', Profile::class)->name('settings.profile');
        Route::get('settings/password', Password::class)->name('settings.password');
        Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    });

  Route::get('/debug-proxy', function (Request $request) {
    return response()->json([
        'scheme' => $request->getScheme(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
        'url' => $request->url(),
        'asset' => asset('css/maestro.css'),
        'app_url' => config('app.url'),
        'forwarded_proto' => $request->header('X-Forwarded-Proto'),
        'remote_addr' => $request->server('REMOTE_ADDR'),
    ]);
});


});

require __DIR__.'/auth.php';
