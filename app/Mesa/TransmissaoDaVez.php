<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;

/** Contexto público da imagem ao vivo; não autoriza nem registra resultados. */
final class TransmissaoDaVez
{
    /** @return array{mesa: string, partida: int, rodada: int, desempate: int, participante: string}|null */
    public static function contexto(Mesa $mesa): ?array
    {
        $partida = $mesa->partidaAtiva();
        $participante = $partida?->participanteJogando();
        if ($participante === null || ! CatalogoDeJogos::transmiteAoVivo($partida->configuracao()->jogo)) {
            return null;
        }

        return [
            'mesa' => $mesa->id,
            'partida' => count($mesa->partidas()),
            'rodada' => $partida->rodadaAtual(),
            'desempate' => $partida->desempateAtual(),
            'participante' => $participante,
        ];
    }
}
