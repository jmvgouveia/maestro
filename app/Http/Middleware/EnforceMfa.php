<?php

namespace App\Http\Middleware;

use App\Filament\Pages\MfaSetup;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnforceMfa
{
    public const SESSION_KEY = 'mfa.confirmed_user_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $impersonation = app(ImpersonateManager::class);

        if (! $user instanceof User || $request->routeIs('logout') || str_ends_with((string) $request->route()?->getName(), '.auth.logout')) {
            return $next($request);
        }

        if ($impersonation->isImpersonating()) {
            try {
                $impersonator = $impersonation->getImpersonator();
            } catch (Throwable) {
                $impersonator = null;
            }

            if (
                $impersonator instanceof User
                && $impersonator->isSuperAdmin()
                && $impersonator->hasTwoFactorEnabled()
                && (int) $request->session()->get(self::SESSION_KEY) === $impersonator->getKey()
            ) {
                if (! $user->canAccessPanel(Filament::getPanel('admin'))) {
                    $impersonation->leave();

                    return redirect()->to(Filament::getPanel('admin')->getUrl());
                }

                return $next($request);
            }

            $impersonation->leave();

            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
        }

        if ($user->isMfaExemptGuardian()) {
            return $next($request);
        }

        if ($user->hasTwoFactorEnabled()) {
            if ((int) $request->session()->get(self::SESSION_KEY) === $user->getKey()) {
                return $next($request);
            }

            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
        }

        if (! $user->isMfaGraceExpired() || $request->routeIs(MfaSetup::getRouteName('admin'))) {
            return $next($request);
        }

        return redirect()->to(MfaSetup::getUrl(panel: 'admin'));
    }
}
