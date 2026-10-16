<?php

namespace App\Filament\Widgets;

use App\Painel\ResumoDoPainel;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Números do dia a dia do RANÇO. As contas ficam em ResumoDoPainel; aqui só a apresentação. */
class EstatisticasGeraisWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $resumo = app(ResumoDoPainel::class);
        $inativas = $resumo->mesasInativas();

        return [
            Stat::make('Usuários cadastrados', $this->numero($resumo->usuariosCadastrados()))
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('Online agora', $this->numero($resumo->onlineAgora()))
                ->icon(Heroicon::OutlinedSignal)
                ->description('Atividade nos últimos 2 minutos')
                ->descriptionColor('success'),
            Stat::make('Mesas abertas', $this->numero($resumo->mesasAbertas()))
                ->icon(Heroicon::OutlinedSquares2x2),
            Stat::make('Mesas inativas', $this->numero($inativas))
                ->icon(Heroicon::OutlinedMoon)
                ->description('Sem atividade há 15 min e ninguém online')
                ->descriptionColor($inativas > 0 ? 'warning' : 'gray'),
            Stat::make('Novos hoje', $this->numero($resumo->novosUsuariosHoje()))
                ->icon(Heroicon::OutlinedUserPlus),
            Stat::make('Criadas hoje', $this->numero($resumo->mesasCriadasHoje()))
                ->icon(Heroicon::OutlinedPlusCircle),
            Stat::make('Finalizadas hoje', $this->numero($resumo->mesasFinalizadasHoje()))
                ->icon(Heroicon::OutlinedFlag),
        ];
    }

    private function numero(int $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }
}
