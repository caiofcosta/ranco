<?php

declare(strict_types=1);

namespace App\Filament\Resources\Opcoes;

use App\Filament\Resources\Opcoes\Pages\GerenciarOpcoes;
use App\Models\Opcao;
use App\Opcoes\Lista;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * Listas de opções editáveis (ex.: sugestões de nome de mesa). O valor não muda depois de criado;
 * para tirar uma opção da tela, desativa-se. Não há exclusão.
 */
class OpcaoResource extends Resource
{
    protected static ?string $model = Opcao::class;

    protected static ?string $slug = 'opcoes';

    protected static ?string $modelLabel = 'opção';

    protected static ?string $pluralModelLabel = 'opções';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $recordTitleAttribute = 'rotulo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('lista')
                ->label('Lista')
                ->options(self::listas())
                ->required()
                ->disabledOn('edit'),
            Select::make('categoria')
                ->label('Turma')
                ->options(Opcao::CATEGORIAS_NOMES_DE_MESA)
                ->default('generico')
                ->required(),
            TextInput::make('rotulo')
                ->label('Rótulo')
                ->helperText('O que aparece na tela.')
                ->required()
                ->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(static function (Get $get, Set $set, ?string $state, string $operation): void {
                    if ($operation === 'create' && blank($get('valor'))) {
                        $set('valor', Str::slug((string) $state));
                    }
                }),
            TextInput::make('valor')
                ->label('Valor')
                ->helperText('O que o sistema guarda. Não muda depois de criado.')
                ->required()
                ->maxLength(80)
                ->alphaDash()
                ->disabledOn('edit')
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: static fn (Unique $rule, Get $get): Unique => $rule->where('lista', $get('lista')),
                ),
            TextInput::make('ordem')
                ->label('Ordem')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Toggle::make('ativo')
                ->label('Ativa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('lista')
                    ->label('Lista')
                    ->formatStateUsing(static fn (Lista $state): string => $state->rotulo())
                    ->badge(),
                TextColumn::make('categoria')
                    ->label('Turma')
                    ->formatStateUsing(static fn (string $state): string => Opcao::CATEGORIAS_NOMES_DE_MESA[$state] ?? $state),
                TextColumn::make('rotulo')->label('Rótulo')->searchable(),
                TextColumn::make('valor')->label('Valor')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ordem')->label('Ordem')->sortable(),
                ToggleColumn::make('ativo')->label('Ativa'),
            ])
            ->filters([
                SelectFilter::make('lista')->label('Lista')->options(self::listas()),
                SelectFilter::make('categoria')->label('Turma')->options(Opcao::CATEGORIAS_NOMES_DE_MESA),
                TernaryFilter::make('ativo')->label('Ativa'),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplicar')
                    ->schema([
                        Hidden::make('lista'),
                        TextInput::make('rotulo')->label('Rótulo')->required()->maxLength(120),
                        TextInput::make('valor')
                            ->label('Valor')
                            ->required()
                            ->maxLength(80)
                            ->alphaDash()
                            ->unique(
                                table: Opcao::class,
                                column: 'valor',
                                modifyRuleUsing: static fn (Unique $rule, Get $get): Unique => $rule->where('lista', $get('lista')),
                            ),
                    ])
                    ->fillForm(static fn (Opcao $record): array => [
                        'lista' => $record->lista->value,
                        'rotulo' => $record->rotulo.' (cópia)',
                        'valor' => $record->valor.'-copia',
                    ])
                    ->beforeReplicaSaved(static function (Opcao $replica, array $data): void {
                        $replica->fill(['rotulo' => $data['rotulo'], 'valor' => $data['valor']]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GerenciarOpcoes::route('/'),
        ];
    }

    /** @return array<string, string> */
    private static function listas(): array
    {
        $listas = [];
        foreach (Lista::cases() as $lista) {
            $listas[$lista->value] = $lista->rotulo();
        }

        return $listas;
    }
}
