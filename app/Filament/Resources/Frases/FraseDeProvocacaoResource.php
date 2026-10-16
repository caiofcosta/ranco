<?php

declare(strict_types=1);

namespace App\Filament\Resources\Frases;

use App\Dominio\Provocacao\Perspectiva;
use App\Filament\Resources\Frases\Pages\GerenciarFrases;
use App\Models\FraseDeProvocacao;
use App\Models\MomentoDeProvocacao;
use App\Provocacao\ValidaVariaveisDaFrase;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Frases de provocação. Sem exclusão: para tirar do sorteio, desativa. Duplicar cria um texto novo.
 */
class FraseDeProvocacaoResource extends Resource
{
    protected static ?string $model = FraseDeProvocacao::class;

    protected static ?string $slug = 'frases';

    protected static ?string $modelLabel = 'frase';

    protected static ?string $pluralModelLabel = 'frases';

    protected static ?string $navigationLabel = 'Frases';

    protected static string|UnitEnum|null $navigationGroup = 'Provocações';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $recordTitleAttribute = 'texto';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('texto')
                ->label('Texto')
                ->required()
                ->maxLength(500)
                ->unique(ignoreRecord: true)
                ->rows(3)
                ->rules([self::regraDasVariaveis()]),
            Select::make('intensidade')
                ->label('Intensidade')
                ->options([1 => '1', 2 => '2', 3 => '3'])
                ->required()
                ->default(1),
            Select::make('perspectiva')
                ->label('Perspectiva')
                ->options([
                    Perspectiva::Pessoa->value => 'Pessoa',
                    Perspectiva::Mesa->value => 'Mesa',
                ])
                ->required()
                ->default(Perspectiva::Pessoa->value),
            Select::make('temas')
                ->label('Temas')
                ->multiple()
                ->relationship(titleAttribute: 'codigo')
                ->preload()
                ->searchable()
                ->required(),
            Toggle::make('ativa')
                ->label('Ativa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('temas'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('texto')->label('Texto')->searchable()->wrap()->limit(80),
                TextColumn::make('temas.codigo')->label('Temas')->badge(),
                TextColumn::make('perspectiva')
                    ->label('Perspectiva')
                    ->formatStateUsing(static fn (Perspectiva $state): string => $state === Perspectiva::Pessoa ? 'Pessoa' : 'Mesa'),
                TextColumn::make('intensidade')->label('Intensidade')->sortable(),
                ToggleColumn::make('ativa')->label('Ativa'),
            ])
            ->filters([
                SelectFilter::make('temas')
                    ->label('Tema')
                    ->relationship('temas', 'codigo')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('momento')
                    ->label('Momento')
                    ->options(static fn (): array => MomentoDeProvocacao::query()->orderBy('codigo')->pluck('codigo', 'codigo')->all())
                    ->query(static function (Builder $query, array $data): void {
                        $momento = $data['value'] ?? null;
                        if (! is_string($momento) || $momento === '') {
                            return;
                        }

                        $query->whereExists(static function ($sub) use ($momento): void {
                            $sub->selectRaw('1')
                                ->from('provocacoes_temas as pt')
                                ->join('momentos_temas as mt', 'mt.tema', '=', 'pt.tema')
                                ->whereColumn('pt.provocacao_id', 'provocacoes.id')
                                ->where('mt.momento', $momento);
                        });
                    }),
                SelectFilter::make('perspectiva')
                    ->label('Perspectiva')
                    ->options([
                        Perspectiva::Pessoa->value => 'Pessoa',
                        Perspectiva::Mesa->value => 'Mesa',
                    ]),
                SelectFilter::make('intensidade')
                    ->label('Intensidade')
                    ->options([1 => '1', 2 => '2', 3 => '3']),
                TernaryFilter::make('ativa')->label('Ativa'),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplicar')
                    ->schema([
                        Hidden::make('temas'),
                        Textarea::make('texto')
                            ->label('Texto')
                            ->required()
                            ->maxLength(500)
                            ->unique(table: FraseDeProvocacao::class, column: 'texto')
                            ->rows(3)
                            ->rules([self::regraDasVariaveis()]),
                    ])
                    ->fillForm(static fn (FraseDeProvocacao $record): array => [
                        'texto' => $record->texto.' (cópia)',
                        'temas' => $record->temas->pluck('codigo')->all(),
                    ])
                    ->beforeReplicaSaved(static function (FraseDeProvocacao $replica, array $data): void {
                        $replica->texto = $data['texto'];
                    })
                    ->after(static function (FraseDeProvocacao $replica, FraseDeProvocacao $record): void {
                        $replica->temas()->sync($record->temas()->pluck('codigo')->all());
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GerenciarFrases::route('/'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Validação com acesso a outros campos do formulário (padrão do Filament: closure que recebe Get). */
    private static function regraDasVariaveis(): Closure
    {
        return function (Get $get): Closure {
            return function (string $atributo, mixed $valor, Closure $falhar) use ($get): void {
                $temas = array_values(array_filter(
                    array_map('strval', (array) $get('temas')),
                    static fn (string $tema): bool => $tema !== '',
                ));
                $erro = ValidaVariaveisDaFrase::mensagemDeErro((string) $valor, $temas);
                if ($erro !== null) {
                    $falhar($erro);
                }
            };
        };
    }
}
