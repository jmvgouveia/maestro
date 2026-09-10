<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Notifications\UserActivationNotification;
use App\Services\UserActivationService;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;





class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Administração';
    protected static ?string $navigationLabel = 'Utilizadores';
    protected static ?string $navigationIcon = 'heroicon-s-user-group';



    public static function getLabel(): string
    {
        return 'Utilizador';
    }

    public static function getPluralLabel(): string
    {
        return 'Utilizadores';
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Introduza nome'),
                TextInput::make('email')
                    ->label('E-mail')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Introduza e-mail'),
                Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->visible(fn(): bool => auth()->user()?->isSuperAdmin() ?? false),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->toggleable()
                    ->width('10%'),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-envelope')
                    ->iconColor('primary'),
                TextColumn::make('mfa_status')
                    ->label('MFA')
                    ->state(fn(User $record): string => $record->hasTwoFactorEnabled() ? 'Ativa' : 'Pendente')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Ativa' ? 'success' : 'warning'),
                TextColumn::make('mfa_grace_until')
                    ->label('Prazo MFA')
                    ->date('d/m/Y')
                    ->placeholder('Expirado'),

                TextColumn::make('roles.name')
                    ->label('Funções')
                    ->sortable()
                    ->toggleable()
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Impersonate::make()
                    ->label('Ver como utilizador')
                    ->redirectTo('/maestro'),
                Action::make('renewMfaGrace')
                    ->label('Renovar prazo MFA')
                    ->icon('heroicon-o-clock')
                    ->visible(fn(): bool => auth()->user()?->isSuperAdmin() ?? false)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        abort_unless(auth()->user()?->isSuperAdmin(), 403);

                        $record->forceFill([
                            'mfa_grace_until' => now()->addDays((int) config('two-factor.grace_days')),
                            'mfa_grace_renewed_at' => now(),
                            'mfa_grace_renewed_by' => auth()->id(),
                        ])->save();
                    }),
                Action::make('resetMfa')
                    ->label('Repor MFA')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn(User $record): bool => (auth()->user()?->isSuperAdmin() ?? false) && ! $record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->modalHeading('Repor autenticação multifator')
                    ->modalDescription('A autenticação multifator será desativada e todas as sessões deste utilizador serão terminadas.')
                    ->action(function (User $record): void {
                        abort_unless(auth()->user()?->isSuperAdmin(), 403);
                        abort_if($record->is(auth()->user()), 422, 'Não pode repor o MFA da sua própria conta.');

                        if ($record->twoFactorAuth()->exists()) {
                            $record->disableTwoFactorAuth();
                        }

                        $record->forceFill([
                            'remember_token' => Str::random(60),
                            'mfa_grace_until' => now(),
                            'mfa_grace_renewed_at' => now(),
                            'mfa_grace_renewed_by' => auth()->id(),
                        ])->save();

                        DB::table('sessions')->where('user_id', $record->getKey())->delete();
                    }),
                Action::make('activationCode')
                    ->label('Gerar código de ativação')
                    ->icon('heroicon-o-key')
                    ->visible(fn(User $record): bool => static::canManageActivation() && ! $record->is_active)
                    ->action(function (User $record): void {
                        static::authorizeActivationManagement();

                        $token = app(UserActivationService::class)->issue($record);
                        $activationUrl = route('activation', ['token' => $token]);

                        Notification::make()
                            ->title('Código gerado')
                            ->body("Código: {$token}\nLink: {$activationUrl}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Action::make('sendActivation')
                    ->label('Reenviar convite')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn(User $record): bool => static::canManageActivation() && ! $record->is_active && app(UserActivationService::class)->hasDeliverableEmail($record))
                    ->action(function (User $record): void {
                        static::authorizeActivationManagement();

                        $token = app(UserActivationService::class)->issue($record);
                        $record->notify(new UserActivationNotification($record, $token));

                        Notification::make()
                            ->title('Convite reenviado')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('exportActivationCodes')
                        ->label('Exportar códigos de ativação')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records): StreamedResponse {
                            static::authorizeActivationManagement();

                            $safeCsvValue = static function (mixed $value): string {
                                $value = (string) $value;

                                return preg_match('/^[=+\-@]/', $value) === 1
                                    ? "'{$value}"
                                    : $value;
                            };

                            $rows = [];

                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    continue;
                                }

                                $token = app(UserActivationService::class)->issue($record);
                                $rows[] = array_map($safeCsvValue, [
                                    $record->name,
                                    $record->email,
                                    $token,
                                    route('activation', ['token' => $token]),
                                    now()->addHours(UserActivationService::TOKEN_TTL_HOURS)->toDateTimeString(),
                                ]);
                            }

                            return response()->streamDownload(function () use ($rows): void {
                                $output = fopen('php://output', 'wb');
                                fputcsv($output, ['Nome', 'Email', 'Código', 'Link de ativação', 'Validade']);
                                foreach ($rows as $row) {
                                    fputcsv($output, $row);
                                }
                                fclose($output);
                            }, 'codigos-ativacao.csv', ['Content-Type' => 'text/csv']);
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action): void {
                            $currentUser = auth()->user();

                            if ($currentUser && $action->getRecords()->contains(fn (User $record): bool => $currentUser->is($record))) {
                                throw new AuthorizationException('Não pode eliminar o seu próprio utilizador.');
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    protected static function canManageActivation(): bool
    {
        return auth()->user()?->isSuperAdmin()
            || auth()->user()?->checkPermissionTo('manage user activation');
    }

    protected static function authorizeActivationManagement(): void
    {
        abort_unless(static::canManageActivation(), 403);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
