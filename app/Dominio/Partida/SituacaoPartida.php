<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

enum SituacaoPartida: string
{
    case EmConfiguracao = 'em_configuracao';
    case EmAndamento = 'em_andamento';
    case Encerrada = 'encerrada';
}
