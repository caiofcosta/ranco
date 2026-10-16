<?php

declare(strict_types=1);

namespace App\Filament\Resources\Temas;

use App\Filament\Resources\Temas\Pages\GerenciarTemas;
use App\Models\TemaDeProvocacao;
use App\Provocacao\TipoDeTema;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Temas das frases. O código não muda depois de criado. Sem exclusão.
 */
class TemaDeProvocacaoResource extends Resource
{
    protected static ?string $model = TemaDeProvocacao::class;

    protected static ?string $slug = 'temas';

    protected static ?string $modelLabel = 'tema';

    protected static ?string $pluralModelLabel = 'temas';

    protected static ?string $navigationLabel = 'Temas';

    protected static string|UnitEnum|null $navigationGroup = 'Provocações';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'codigo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')
                ->label('Código')
                ->required()
                ->maxLength(40)
                ->regex('/^[a-z0-9_]+$/')
                ->unique(ignoreRecord: true)
                ->disabledOn('edit')
                ->helperText('Minúsculas, sem acento, só sublinhado. Não muda depois de criado.'),
            Select::make('tipo')
                ->label('Tipo')
                ->options(self::tipos())
                ->required(),
            TextInput::make('significado')
                ->label('Significado')
                ->required()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query
                ->with('momentos')
                ->withCount([
                    'frases as frases_ativas_count' => static fn (Builder $query): Builder => $query->where('ativa', true),
                ]))
            ->defaultSort('codigo')
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(static fn (TipoDeTema $state): string => $state->rotulo())
                    ->badge(),
                TextColumn::make('significado')->label('Significado')->wrap()->limit(60),
                TextColumn::make('momentos.codigo')->label('Momentos')->badge(),
                TextColumn::make('frases_ativas_count')->label('Frases ativas')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Tipo')->options(self::tipos()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GerenciarTemas::route('/'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return array<string, string> */
    private static function tipos(): array
    {
        $tipos = [];
        foreach (TipoDeTema::cases() as $tipo) {
            $tipos[$tipo->value] = $tipo->rotulo();
        }

        return $tipos;
    }
}
