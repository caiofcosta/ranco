<?php

declare(strict_types=1);

namespace App\Navegacao;

/** Os três itens do Resumo da Início (Agora, Jogando, Mesas), a partir do contexto de ContextoMobile. */
final class ResumoDaHome
{
    /** @return list<array{0: string, 1: string, 2: string, 3: string}> área, rótulo, ícone, texto */
    public static function itens(array $contexto): array
    {
        $total = $contexto['total'];
        $participacoes = count($contexto['participacoes']);
        $jogando = $contexto['jogando']['mesa']->nome
            ?? ($participacoes === 1 ? $contexto['participacoes'][0]['mesa']->nome : ($participacoes > 1 ? $participacoes.' participações' : 'Nenhuma mesa ativa'));

        return [
            ['agora', 'Agora', 'bolt', $total ? ($total === 1 ? '1 coisa precisa de você' : $total.' coisas precisam de você') : 'Nada pendente'],
            ['jogando', 'Jogando', 'play', $jogando],
            ['mesas', 'Mesas', 'squares-2x2', count($contexto['administradas']).' de '.$contexto['limite'].' administradas abertas'],
        ];
    }
}
