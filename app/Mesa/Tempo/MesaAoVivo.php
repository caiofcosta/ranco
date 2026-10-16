<?php

declare(strict_types=1);

namespace App\Mesa\Tempo;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\NaoEncontrado;
use App\Dominio\Plataforma\Plataforma;

/** Mesa aberta da qual a pessoa é membro ativo. Mesa encerrada não tem atualização ao vivo. */
final class MesaAoVivo
{
    public static function autorizada(object $usuario, string $id): ?Mesa
    {
        try {
            $mesa = app(Plataforma::class)->mesa($id);
        } catch (NaoEncontrado) {
            return null;
        }

        if ($mesa->situacao() !== SituacaoMesa::Aberta) {
            return null;
        }

        $pessoa = $usuario->identificadorNoDominio();

        // Invisível não fica sentado: não recebe a mesa ao vivo nem aparece na presença.
        return $mesa->membroAtivo($pessoa) && ! app(Plataforma::class)->estaInvisivel($pessoa) ? $mesa : null;
    }

    /**
     * Presença: membros ativos e quem tem convite pendente para a mesa aberta, para o admin ver
     * o convidado online. As atualizações da mesa (canal privado) continuam só para membros.
     */
    public static function presencaAutorizada(object $usuario, string $id): bool
    {
        if (self::autorizada($usuario, $id) !== null) {
            return true;
        }

        $plataforma = app(Plataforma::class);
        if ($plataforma->estaInvisivel($usuario->identificadorNoDominio())) {
            return false;
        }
        foreach ($plataforma->convitesPendentesPara($usuario->identificadorNoDominio()) as $convite) {
            if ($convite->mesaId === $id) {
                try {
                    return $plataforma->mesa($id)->situacao() === SituacaoMesa::Aberta;
                } catch (NaoEncontrado) {
                    return false;
                }
            }
        }

        return false;
    }
}
