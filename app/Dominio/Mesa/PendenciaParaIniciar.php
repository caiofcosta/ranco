<?php

declare(strict_types=1);

namespace App\Dominio\Mesa;

/** O que ainda impede o admin de iniciar a próxima partida da mesa. */
enum PendenciaParaIniciar
{
    /** Não há partida em configuração: falta escolher o jogo. */
    case EscolherJogo;

    /** A partida tem menos jogadores que o mínimo do jogo. */
    case MinimoDeJogadores;
}
