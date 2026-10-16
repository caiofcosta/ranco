<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Social\Convite;
use App\Models\User;

/** O que o admin vê para chamar gente: quem ainda pode ser convidado e o que já está pendente. */
final readonly class ConvitesDaMesa
{
    /**
     * @param  list<array{id: string, nome: string}>  $convidaveis
     * @param  list<array{id: string, nome: string, pessoa: string}>  $pendentes  `id` é o do convite; `pessoa`, quem foi convidado.
     */
    public function __construct(
        public bool $visivel,
        public bool $convidarEPrincipal,
        public array $convidaveis,
        public array $pendentes,
    ) {}

    public static function de(Plataforma $plataforma, Mesa $mesa, string $usuario): self
    {
        if ($mesa->admin() !== $usuario || $mesa->situacao() !== SituacaoMesa::Aberta) {
            return new self(false, false, [], []);
        }

        $pendentes = $plataforma->convitesPendentesDaMesa($mesa->id);
        $jaConvidados = array_map(static fn (Convite $convite): string => $convite->para, $pendentes);
        $naMesa = $mesa->membrosAtivos();

        $convidaveis = [];
        foreach ($plataforma->amigosDe($usuario) as $amigo) {
            if (in_array($amigo, $naMesa, true) || in_array($amigo, $jaConvidados, true)) {
                continue;
            }
            $nome = User::query()->find($amigo)?->name;
            if (! is_string($nome) || $nome === '') {
                continue;
            }
            $convidaveis[] = ['id' => $amigo, 'nome' => $nome];
        }
        usort($convidaveis, static fn (array $a, array $b): int => $a['nome'] <=> $b['nome']);

        $pendentesNaTela = [];
        foreach ($pendentes as $convite) {
            $pendentesNaTela[] = [
                'id' => $convite->id,
                'nome' => User::query()->find($convite->para)?->name ?? 'Alguém',
                'pessoa' => $convite->para,
            ];
        }

        return new self(
            true,
            count($naMesa) === 1,
            $convidaveis,
            $pendentesNaTela,
        );
    }
}
