<?php

declare(strict_types=1);

namespace Tests\Suporte;

use App\Mesa\LacreDoTempo;

/** Envio de tempo como a tela faz: com o lacre calculado sobre a jogada e o tempo. */
trait LacraOTempo
{
    private function lacrado(array $dados): array
    {
        if (! is_string($dados['jogada'] ?? null) || ! isset($dados['tempo'])) {
            return $dados;
        }

        return [...$dados, 'lacre' => LacreDoTempo::de($dados['jogada'], (int) $dados['tempo'])];
    }
}
