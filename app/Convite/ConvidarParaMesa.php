<?php

declare(strict_types=1);

namespace App\Convite;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocador;
use App\Dominio\Social\Convite;
use App\Mesa\NomeDaMesa;
use App\Models\User;
use App\Notifications\ConviteDeMesa;

/**
 * Convite de mesa pelo aplicativo: grava no domínio e manda o e-mail. A tela de convite precisa
 * passar por aqui; `Plataforma::convidar` sozinho não avisa ninguém.
 */
final class ConvidarParaMesa
{
    public function __construct(
        private readonly Plataforma $plataforma,
        private readonly Provocador $provocador,
    ) {
    }

    public function executar(string $mesaId, User $admin, User $convidado): Convite
    {
        $convite = $this->plataforma->convidar(
            $mesaId,
            $admin->identificadorNoDominio(),
            $convidado->identificadorNoDominio(),
        );
        ConviteRecebido::dispatch($convidado->identificadorNoDominio());

        $nomeConvidante = $admin->name;
        $convidado->notify(new ConviteDeMesa(
            nomeConvidante: $nomeConvidante,
            nomeMesa: NomeDaMesa::de($this->plataforma->mesa($mesaId)),
            urlConvite: LinkDoConvite::assinado($convite->id),
            mensagemTom: $this->provocador->sortear(
                Momento::ConvitePorEmail,
                1,
                ['admin' => $nomeConvidante],
            ) ?? '',
            emailDestinatario: $convidado->email,
        ));

        return $convite;
    }
}
