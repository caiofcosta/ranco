<?php

declare(strict_types=1);

namespace App\Infra\Memoria;

use App\Dominio\Jogos\RepositorioDeDisponibilidadeDeJogos;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Relogio;

final class PlataformaEmMemoria
{
    public static function criar(Relogio $relogio): Plataforma
    {
        return new Plataforma(
            $relogio,
            new RepositorioDeMesasEmMemoria(),
            new RepositorioDeConvitesEmMemoria(),
            new RepositorioSocialEmMemoria(),
            new TransacaoEmMemoria(),
            new class implements RepositorioDeDisponibilidadeDeJogos
            {
                public function estaDisponivel(string $jogo): bool
                {
                    return true;
                }
            },
            notificador: null,
            visibilidade: new RepositorioDeVisibilidadeEmMemoria(),
        );
    }
}
