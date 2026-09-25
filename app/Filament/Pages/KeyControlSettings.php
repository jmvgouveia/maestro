<?php

namespace App\Filament\Pages;

use App\Models\KeyControlReportRecipient;
use App\Models\KeyControlSetting;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class KeyControlSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.key-control-settings';

    protected static ?string $slug = 'definicoes-controlo-chaves';

    protected static ?string $navigationGroup = 'Porteiro';

    protected static ?string $navigationLabel = 'Definições de chaves';

    protected static ?string $title = 'Definições do controlo de chaves';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage key control settings')
            || auth()->user()?->can('manage key control report recipients');
    }

    public function mount(): void
    {
        $recipients = KeyControlReportRecipient::query()->get()->map(fn (KeyControlReportRecipient $recipient): array => [
            'type' => $recipient->type,
            'name' => $recipient->name,
            'user_id' => $recipient->user_id,
            'email' => $recipient->email,
            'is_active' => $recipient->is_active,
            'report_types' => $recipient->report_types ?? [],
        ])->all();

        $this->form->fill([
            'daily_closure_time' => KeyControlSetting::value('daily_closure_time', '23:59'),
            'recipients' => $recipients,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('daily_closure_time')
                ->label('Hora do fecho diário')
                ->type('time')
                ->required()
                ->rules(['date_format:H:i']),
            Repeater::make('recipients')
                ->label('Destinatários de relatórios')
                ->schema([
                     Select::make('type')
                        ->label('Tipo de destinatário')
                        ->options(['user' => 'Utilizador do sistema', 'external' => 'Email externo'])
                        ->required()
                        ->live(),
                    TextInput::make('name')
                        ->label('Nome do destinatário')
                        ->required()
                        ->visible(fn (\Filament\Forms\Get $get): bool => $get('type') === 'external'),
                    Select::make('user_id')
                        ->label('Utilizador')
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (\Filament\Forms\Get $get): bool => $get('type') === 'user'),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->visible(fn (\Filament\Forms\Get $get): bool => $get('type') === 'external'),
                    Select::make('report_types')
                        ->label('Relatórios')
                        ->multiple()
                        ->options(['key_control_daily_closure' => 'Fecho diário do controlo de chaves'])
                        ->required(),
                    Toggle::make('is_active')->label('Ativo')->default(true),
                ])
                ->defaultItems(0)
                ->itemLabel(function (array $state): ?string {
                    $reports = collect($state['report_types'] ?? [])->map(fn (string $type): string => match ($type) {
                        'key_control_daily_closure' => 'Fecho diário',
                        default => $type,
                    })->implode(', ');

                    if (($state['type'] ?? null) === 'external') {
                        return 'Externo: '.($state['name'] ?? 'Sem nome').' · '.($reports ?: 'Sem relatório');
                    }

                    return 'Utilizador: '.(User::find($state['user_id'] ?? null)?->name ?? 'Não selecionado').' · '.($reports ?: 'Sem relatório');
                })
                ->collapsible()
                ->addActionLabel('Adicionar destinatário'),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (auth()->user()?->can('manage key control settings')) {
            KeyControlSetting::put('daily_closure_time', $data['daily_closure_time']);
        }

        if (auth()->user()?->can('manage key control report recipients')) {
            KeyControlReportRecipient::query()->delete();

            foreach ($data['recipients'] ?? [] as $recipient) {
                KeyControlReportRecipient::create($recipient);
            }
        }

        Notification::make()->title('Definições guardadas.')->success()->send();
    }

    public function getRecipientSummaryProperty(): array
    {
        return collect($this->data['recipients'] ?? [])->map(function (array $recipient): array {
            $user = ! empty($recipient['user_id']) ? User::find($recipient['user_id']) : null;

            return [
                'name' => $recipient['type'] === 'external'
                    ? ($recipient['name'] ?? 'Sem nome')
                    : ($user?->name ?? 'Utilizador não selecionado'),
                'email' => $recipient['type'] === 'external'
                    ? ($recipient['email'] ?? 'Sem email')
                    : ($user?->email ?? 'Sem email'),
                'type' => $recipient['type'] === 'external' ? 'Externo' : 'Utilizador do sistema',
                'reports' => collect($recipient['report_types'] ?? [])->map(fn (string $type): string => match ($type) {
                    'key_control_daily_closure' => 'Fecho diário do controlo de chaves',
                    default => $type,
                })->values()->all(),
                'active' => (bool) ($recipient['is_active'] ?? false),
            ];
        })->values()->all();
    }
}
