<?php

namespace App\Filament\Widgets;

use App\Painel\ResumoDoPainel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Cadastros e mesas criadas por dia, hoje e os 6 dias anteriores (dias de Brasília), sem gráfico. */
class UltimosSeteDiasWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Últimos 7 dias')
            ->records(static fn (): array => array_column(app(ResumoDoPainel::class)->ultimosSeteDias(), null, 'data'))
            ->columns([
                TextColumn::make('rotulo')->label('Dia'),
                TextColumn::make('usuarios')->label('Novos usuários')->numeric()->alignEnd(),
                TextColumn::make('mesas')->label('Mesas criadas')->numeric()->alignEnd(),
            ])
            ->paginated(false)
            ->poll('60s');
    }
}
