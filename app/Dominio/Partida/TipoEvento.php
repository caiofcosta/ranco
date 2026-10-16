<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

enum TipoEvento: string
{
    case PartidaIniciada = 'partida_iniciada';
    case PartidaPausada = 'partida_pausada';
    case PartidaRetomada = 'partida_retomada';
    case PartidaEncerrada = 'partida_encerrada';
    case EntrouNaPartida = 'entrou_na_partida';
    case RetornouAPartida = 'retornou_a_partida';
    case AbandonouAPartida = 'abandonou_a_partida';
    case RemovidoDaPartida = 'removido_da_partida';
    case VezPulada = 'vez_pulada';
    case TentativaIniciada = 'tentativa_iniciada';
    case TentativaConcluida = 'tentativa_concluida';
    case TentativaInterrompida = 'tentativa_interrompida';
    case DesempateIniciado = 'desempate_iniciado';
    case RodadaConcluida = 'rodada_concluida';
}
