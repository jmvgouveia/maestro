<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailAuditResource\Pages;
use App\Models\EmailAudit;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailAuditResource extends Resource
{
    protected static ?string $model = EmailAudit::class;
    protected static ?string $navigationGroup = 'Administração';
    protected static ?string $navigationLabel = 'Auditoria de Emails';
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 99;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attempted_at')->label('Tentativa')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('recipient_email')->label('Destinatário')->searchable(),
                TextColumn::make('subject')->label('Assunto')->searchable()->limit(60),
                TextColumn::make('notification_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->searchable(),
                TextColumn::make('mailer')->label('Mailer')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()->sortable(),
                TextColumn::make('accepted_at')->label('Aceite SMTP')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('failed_at')->label('Falha')->dateTime('d/m/Y H:i:s')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    EmailAudit::STATUS_SENDING => 'A enviar',
                    EmailAudit::STATUS_ACCEPTED => 'Aceite pelo SMTP',
                    EmailAudit::STATUS_FAILED => 'Falhou',
                ]),
                Filter::make('recipient_email')
                    ->label('Destinatário')
                    ->form([TextInput::make('value')->label('Email')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query) => $query->where('recipient_email', 'like', '%'.$data['value'].'%'),
                    )),
                Filter::make('notification_type')
                    ->label('Tipo de notificação')
                    ->form([TextInput::make('value')->label('Classe')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query) => $query->where('notification_type', 'like', '%'.$data['value'].'%'),
                    )),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('attempted_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEmailAudits::route('/')];
    }
}
