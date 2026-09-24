<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Student;
use App\Models\User;
use App\Services\UserActivationService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions as ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                Section::make('Dados pessoais')
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
                    ])
                    ->columns(2),
                Section::make('Conta')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Conta ativa')
                            ->helperText(fn (?User $record): ?string => $record?->isPendingActivation()
                                ? 'A conta só pode ser ativada através do convite ou código de ativação.'
                                : null)
                            ->visible(fn (?User $record): bool => $record !== null)
                            ->disabled(fn (?User $record): bool => ($record?->isPendingActivation() ?? false)
                                || ! (auth()->user()?->isSuperAdmin() ?? false))
                            ->dehydrated(fn (?User $record): bool => $record !== null
                                && ! ($record?->isPendingActivation() ?? false)
                                && (auth()->user()?->isSuperAdmin() ?? false)),
                        Placeholder::make('activated_at_display')
                            ->label('Ativa desde')
                            ->content(fn (?User $record): string => $record?->activated_at?->format('d/m/Y H:i') ?? 'Não registado'),
                    ])
                    ->columns(2)
                    ->visible(fn (?User $record): bool => $record !== null),
                Section::make('Autenticação multifator')
                    ->schema([
                        Placeholder::make('mfa_status')
                            ->label('Estado')
                            ->content(fn (?User $record): string => $record?->hasTwoFactorEnabled() ? 'Ativa' : 'Pendente')
                            ->visible(fn (?User $record): bool => $record !== null),
                        DateTimePicker::make('mfa_grace_until')
                            ->label('Prazo MFA')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?User $record): bool => $record !== null),
                        Toggle::make('mfa_required')
                            ->label('Exigir configuração MFA')
                            ->default(true)
                            ->helperText('Se ativo, o utilizador recebe o aviso e será obrigado a configurar MFA após o prazo. Se desativado, não recebe o aviso, mas pode configurar MFA voluntariamente no menu do utilizador.')
                            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                        Placeholder::make('mfa_explanation')
                            ->label('Diferença entre as opções')
                            ->content('Exigir configuração MFA controla a obrigatoriedade. Desativar MFA remove um MFA que já esteja configurado e termina as sessões do utilizador.')
                            ->visible(fn (?User $record): bool => $record !== null),
                        ActionGroup::make([
                            Action::make('renewMfaGrace')
                                ->label('Renovar prazo MFA')
                                ->icon('heroicon-o-clock')
                                ->visible(fn (?User $record): bool => ($record?->requiresMfaSetup() ?? false)
                                    && ! ($record?->hasTwoFactorEnabled() ?? false)
                                    && (auth()->user()?->isSuperAdmin() ?? false))
                                ->requiresConfirmation()
                                ->action(function (?User $record): void {
                                    abort_unless(auth()->user()?->isSuperAdmin(), 403);

                                    $record?->forceFill([
                                        'mfa_grace_until' => now()->addDays((int) config('two-factor.grace_days')),
                                        'mfa_grace_renewed_at' => now(),
                                        'mfa_grace_renewed_by' => auth()->id(),
                                    ])->save();
                                }),
                            Action::make('disableMfa')
                                ->label('Desativar MFA')
                                ->icon('heroicon-o-shield-exclamation')
                                ->color('danger')
                                ->visible(fn (?User $record): bool => ($record?->hasTwoFactorEnabled() ?? false)
                                    && ! $record->is(auth()->user())
                                    && (auth()->user()?->isSuperAdmin() ?? false))
                                ->requiresConfirmation()
                                ->modalHeading('Desativar autenticação multifator')
                                ->modalDescription('O MFA será desativado e todas as sessões deste utilizador serão terminadas.')
                                ->action(function (?User $record): void {
                                    abort_unless(auth()->user()?->isSuperAdmin(), 403);
                                    abort_if($record?->is(auth()->user()), 422, 'Não pode desativar o MFA da sua própria conta.');

                                    if ($record?->twoFactorAuth()->exists()) {
                                        $record->disableTwoFactorAuth();
                                    }

                                    $record?->forceFill([
                                        'remember_token' => Str::random(60),
                                        'mfa_grace_until' => now(),
                                        'mfa_grace_renewed_at' => now(),
                                        'mfa_grace_renewed_by' => auth()->id(),
                                    ])->save();

                                    DB::table('sessions')->where('user_id', $record?->getKey())->delete();
                                }),
                        ])
                            ->visible(fn (?User $record): bool => $record !== null),
                    ])
                    ->columns(2)
                    ->visible(fn (?User $record): bool => $record !== null),
                Section::make('Ativação da conta')
                    ->schema([
                        ActionGroup::make([
                            Action::make('activationCode')
                                ->label('Gerar código de ativação')
                                ->icon('heroicon-o-key')
                                ->visible(fn (?User $record): bool => $record?->isPendingActivation() ?? false)
                                ->action(function (?User $record): void {
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
                                ->visible(fn (?User $record): bool => ($record?->isPendingActivation() ?? false)
                                    && app(UserActivationService::class)->hasDeliverableEmail($record))
                                ->action(function (?User $record): void {
                                    static::authorizeActivationManagement();

                                    $token = app(UserActivationService::class)->issue($record);
                                    $record?->notify(new \App\Notifications\UserActivationNotification($record, $token));

                                    Notification::make()
                                        ->title('Convite reenviado')
                                        ->success()
                                        ->send();
                                }),
                    ])
                    ->visible(fn (?User $record): bool => ($record?->isPendingActivation() ?? false)
                        && static::canManageActivation()),
                    ]),
                Section::make('Funções')
                    ->schema([
                        Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->live()
                            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                    ]),
                Section::make('Encarregado de Educação')
                    ->schema([
                        Select::make('guardianStudents')
                            ->label('Alunos associados')
                            ->relationship('guardianStudents', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Student $record): string => "{$record->number} - {$record->name}")
                            ->multiple()
                            ->searchable(['number', 'name'])
                            ->preload()
                            ->helperText('Utilizado para definir os alunos disponíveis a um Encarregado de Educação.'),
                    ])
                    ->visible(fn (Get $get, ?User $record): bool => static::hasGuardianRole($get('roles'), $record)
                        && static::canManageGuardianStudents()),
            ]);
    }

    protected static function hasGuardianRole(mixed $roleIds, ?User $record): bool
    {
        if (is_array($roleIds)) {
            return Role::query()
                ->whereKey($roleIds)
                ->where('name', User::ROLE_GUARDIAN)
                ->exists();
        }

        return $record?->isGuardian() ?? false;
    }

    protected static function canManageGuardianStudents(): bool
    {
        return auth()->user()?->isSuperAdmin()
            || auth()->user()?->checkPermissionTo('update User');
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
                TextColumn::make('account_status')
                    ->label('Estado')
                    ->state(fn (User $record): string => $record->isPendingActivation()
                        ? 'Pendente de ativação'
                        : ($record->is_active ? 'Ativa' : 'Desativada'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Ativa' => 'success',
                        'Desativada' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('activated_at')
                    ->label('Ativa desde')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Não registado')
                    ->toggleable(),
                TextColumn::make('last_login_at')
                    ->label('Último acesso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca entrou')
                    ->toggleable(),
                TextColumn::make('mfa_status')
                    ->label('MFA')
                    ->state(fn (User $record): string => $record->hasTwoFactorEnabled() ? 'Ativa' : 'Pendente')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Ativa' ? 'success' : 'warning')
                    ->toggleable(),

                TextColumn::make('roles.name')
                    ->label('Funções')
                    ->sortable()
                    ->toggleable()
                    ->limitList(1)
                    ->expandableLimitedList(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Tipo de utilizador')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Impersonate::make()
                    ->label('Ver como utilizador')
                    ->redirectTo('/maestro'),
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
                                if (! $record->isPendingActivation()) {
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
