<?php

declare(strict_types=1);

namespace App\Filament\Resources\Momentos;

use App\Filament\Resources\Momentos\Pages\GerenciarMomentos;
use App\Models\MomentoDeProvocacao;
use App\Provocacao\SorteiaExemploDoMomento;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Momentos do sistema. O Painel não cria nem apaga: só edita a descrição e os temas aceitos.
 */
class MomentoDeProvocacaoResource extends Resource
{
    protected static ?string $model = MomentoDeProvocacao::class;

    protected static ?string $slug = 'momentos';

    protected static ?string $modelLabel = 'momento';

    protected static ?string $pluralModelLabel = 'momentos';

    protected static ?string $navigationLabel = 'Momentos';

    protected static string|UnitEnum|null $navigationGroup = 'Provocações';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'codigo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')
                ->label('Código')
                ->disabled(),
            TextInput::make('variaveis')
                ->label('Variáveis')
                ->disabled(),
            TextInput::make('descricao')
                ->label('Descrição')
                ->required()
                ->maxLength(255),
            Select::make('temas')
                ->label('Temas aceitos')
                ->multiple()
                ->relationship(titleAttribute: 'codigo')
                ->preload()
                ->searchable()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with(['temas.frases']))
            ->defaultSort('codigo')
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable(),
                TextColumn::make('variaveis')
                    ->label('Variáveis')
                    ->formatStateUsing(static fn (?string $state): string => $state === null || $state === '' ? '—' : $state),
                TextColumn::make('descricao')->label('Descrição')->wrap()->limit(60),
                TextColumn::make('temas.codigo')->label('Temas')->badge(),
                TextColumn::make('frases_utilizaveis')
                    ->label('Frases utilizáveis')
                    ->getStateUsing(static fn (MomentoDeProvocacao $record): int => $record->frasesUtilizaveis())
                    ->badge()
                    ->color(static fn (int $state): string => $state === 0 ? 'danger' : 'gray'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('sortearExemplo')
                    ->label('Sortear exemplo')
                    ->action(static function (MomentoDeProvocacao $record): void {
                        $frase = SorteiaExemploDoMomento::criar()->sortear($record);
                        Notification::make()
                            ->title($frase ?? 'Nenhuma frase utilizável neste momento.')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GerenciarMomentos::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
