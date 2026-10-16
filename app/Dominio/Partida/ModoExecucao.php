<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

/** Sequencial: uma pessoa por vez, na ordem. Paralelo: a rodada abre e cada pessoa joga quando quiser, no prazo. */
enum ModoExecucao: string
{
    case Sequencial = 'sequencial';
    case Paralelo = 'paralelo';
}
