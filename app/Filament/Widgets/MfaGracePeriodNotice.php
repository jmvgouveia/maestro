<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class MfaGracePeriodNotice extends Widget
{
    protected static string $view = 'filament.widgets.mfa-grace-period-notice';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->requiresMfaSetup()
            && ! $user->hasTwoFactorEnabled()
            && ! $user->isMfaGraceExpired();
    }
}
