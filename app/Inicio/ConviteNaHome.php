<?php

declare(strict_types=1);

namespace App\Inicio;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Social\Convite;
use App\Mesa\NomeDaMesa;
use App\Mesa\PessoasDaMesa;
use App\Models\User;

/** Card de convite recebido na home: quem chamou, para qual mesa e quantas pessoas já estão nela. */
final readonly class ConviteNaHome
{
    public function __construct(
        public string $id,
        public string $nomeConvidante,
        public string $nomeMesa,
        public string $mesaId = '',
        public string $iniciais = '',
        public int $quantasPessoas = 0,
        public ?string $foto = null,
    ) {}

    /** @return list<self> */
    public static function para(Plataforma $plataforma, string $usuario): array
    {
        return array_values(array_filter(array_map(
            static function (Convite $convite) use ($plataforma): ?self {
                $convidante = User::query()->find($convite->de);
                $nome = $convidante?->name;
                if (! is_string($nome) || $nome === '') {
                    return null;
                }
                $mesa = $plataforma->mesa($convite->mesaId);

                return new self(
                    $convite->id,
                    $nome,
                    NomeDaMesa::de($mesa),
                    $convite->mesaId,
                    PessoasDaMesa::iniciais($nome),
                    count($mesa->membrosAtivos()),
                    $convidante->google_avatar_url,
                );
            },
            $plataforma->convitesPendentesPara($usuario),
        )));
    }
}
