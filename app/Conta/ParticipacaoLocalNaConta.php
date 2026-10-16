<?php

declare(strict_types=1);

namespace App\Conta;

use App\Dominio\NaoEncontrado;
use App\Dominio\Plataforma\Plataforma;
use App\Mesa\NomeDaMesa;

/** Participação local pendente, do jeito que a conta mostra: nome e mesa, nunca o identificador. */
final readonly class ParticipacaoLocalNaConta
{
    public function __construct(
        public string $id,
        public string $nome,
        public string $mesa,
    ) {}

    /** @return list<self> */
    public static function pendentes(Plataforma $plataforma, string $email): array
    {
        $lista = [];
        foreach ($plataforma->participacoesLocaisPendentes($email) as $local) {
            $lista[] = new self($local->id, $local->nome, self::nomeDaMesa($plataforma, $local->id));
        }

        return $lista;
    }

    private static function nomeDaMesa(Plataforma $plataforma, string $idLocal): string
    {
        $mesaId = strstr($idLocal, ':local:', true);
        if ($mesaId === false) {
            return 'Mesa';
        }

        try {
            return NomeDaMesa::de($plataforma->mesa($mesaId));
        } catch (NaoEncontrado) {
            return 'Mesa';
        }
    }
}
