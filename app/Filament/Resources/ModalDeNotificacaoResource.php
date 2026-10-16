<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ModalDeNotificacaoResource\Pages\GerenciarModaisDeNotificacao;
use App\Models\ModalDeNotificacao;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ModalDeNotificacaoResource extends Resource
{
    private const TIPOS = [
        'onboarding' => 'Boas-vindas',
        'novo_jogo' => 'Novo jogo',
        'aviso' => 'Aviso',
        'destaque' => 'Destaque',
    ];

    protected static ?string $model = ModalDeNotificacao::class;

    protected static ?string $slug = 'modais-de-notificacao';

    protected static ?string $navigationLabel = 'Modais de notificação';

    protected static ?string $modelLabel = 'modal de notificação';

    protected static ?string $pluralModelLabel = 'modais de notificação';

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Básico')->columns(2)->schema([
                TextInput::make('titulo')->label('Título')->required()->maxLength(255),
                Select::make('tipo')->label('Tipo')->options(self::TIPOS)->required()->default('onboarding'),
                Toggle::make('ativo')->label('Ativo')->default(true),
            ]),
            Section::make('Conteúdo')->schema([
                Textarea::make('conteudo.texto_inicial')->label('Texto inicial')->required()->rows(2),
                Repeater::make('conteudo.secoes')
                    ->label('Seções')
                    ->schema([
                        TextInput::make('titulo')->label('Título')->required(),
                        Textarea::make('texto')->label('Texto')->required()->rows(2),
                    ])
                    ->collapsible()
                    ->addActionLabel('Adicionar seção')
                    ->defaultItems(0),
                Textarea::make('conteudo.texto_final')->label('Texto final')->required()->rows(2),
            ]),
            Section::make('Período')->columns(2)->schema([
                DateTimePicker::make('data_inicio')->label('Início'),
                DateTimePicker::make('data_fim')->label('Término'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Título')->searchable()->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TIPOS[$state] ?? $state),
                IconColumn::make('ativo')->label('Ativo')->boolean()->sortable(),
                TextColumn::make('data_inicio')->label('Início')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('data_fim')->label('Término')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dismissoes_count')->label('Visualizações')->counts('dismissoes')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('ativo')->label('Ativo'),
                SelectFilter::make('tipo')->label('Tipo')->options(self::TIPOS),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GerenciarModaisDeNotificacao::route('/'),
        ];
    }
}
