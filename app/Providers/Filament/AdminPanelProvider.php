<?php

namespace App\Providers\Filament;

use Althinect\FilamentSpatieRolesPermissions\FilamentSpatieRolesPermissionsPlugin;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\MfaSetup;
use App\Filament\Pages\MyAccount;
use App\Filament\Pages\Auth\Login;
use App\Filament\Widgets\BuildingsOverview;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\StatsOverviewAP;
use App\Filament\Widgets\StatsOverviewRH;
use App\Filament\Widgets\StudentsOverview;
use App\Filament\Widgets\TeachersOverview;
use App\Filament\Widgets\MfaGracePeriodNotice;
use App\Http\Middleware\EnforceMfa;
use App\Http\Middleware\EnforceReadOnlyImpersonation;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('maestro')
            ->login(Login::class)
            ->passwordReset()
            ->userMenuItems([
                MenuItem::make()
                    ->label('Autenticação multifator')
                    ->url(fn (): string => MfaSetup::getUrl(panel: 'admin'))
                    ->icon('heroicon-o-shield-check'),
                MenuItem::make()
                    ->label('A Minha Conta')
                    ->url(fn (): string => MyAccount::getUrl(panel: 'admin'))
                    ->icon('heroicon-o-user'),
            ])

            ->colors([
                'primary' => Color::hex('#063B82'),
            ])
            ->font('Inter')
            ->darkMode()
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarWidth('18rem')
            ->favicon(secure_asset('images/maestro-symbol.svg'))
            ->assets([
                Css::make(
                    'maestro',
                    secure_asset('css/maestro.css') . '?v=' . filemtime(public_path('css/maestro.css'))
                ),
                Js::make(
                    'maestro-charts',
                    secure_asset('js/maestro-charts.js')
                ),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.components.topbar-user-summary')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.auth.login-footer')->render(),
            )

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            //   ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                MfaGracePeriodNotice::class,
                StatsOverviewAP::class,
                StatsOverviewRH::class,
                StatsOverview::class,
                StudentsOverview::class,
                TeachersOverview::class,
                BuildingsOverview::class,

                /* Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class, */
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                EnforceMfa::class,
                Authenticate::class,
                EnforceReadOnlyImpersonation::class,
            ], isPersistent: true)
            ->plugin(FilamentSpatieRolesPermissionsPlugin::make())
            ->sidebarFullyCollapsibleOnDesktop()
           ->brandName('MAESTRO')
            ->brandLogo(secure_asset('images/maestro-logo-light.svg'))
            ->darkModeBrandLogo(secure_asset('images/maestro-logo-dark.svg'))
            ->brandLogoHeight(fn (): string => request()->is('maestro/login') ? '10rem' : '2.625rem')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Académico'),
                NavigationGroup::make()
                    ->label('Horários'),
                NavigationGroup::make()
                    ->label('Recursos'),
                NavigationGroup::make()
                    ->label('Administração')
                    ->collapsible(),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('5s');
    }
}
