<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Conquista\RepositorioDeMedalhas;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class RepositorioDeMedalhasNoBanco implements RepositorioDeMedalhas
{
    public function dasPessoas(array $pessoas): array
    {
        $linhas = DB::table('medalhas')
            ->whereIn('pessoa', $pessoas)
            ->orderByRaw('COALESCE(partida_criada_em, concedida_em) DESC')
            ->orderByDesc('id')
            ->get(['id', 'colocacao', 'empate', 'jogo', 'mesa_nome', 'frase', 'concedida_em', 'partida_criada_em']);

        $medalhas = [];
        foreach ($linhas as $linha) {
            $medalhas[] = [
                'id' => (int) $linha->id,
                'colocacao' => (int) $linha->colocacao,
                'empate' => (bool) $linha->empate,
                'jogo' => $linha->jogo,
                'mesa' => $linha->mesa_nome,
                'frase' => $linha->frase,
                'concedidaEm' => new DateTimeImmutable($linha->concedida_em, new DateTimeZone('UTC')),
                'partidaCriadaEm' => self::instante($linha->partida_criada_em),
            ];
        }

        return $medalhas;
    }

    private static function instante(?string $valor): ?DateTimeImmutable
    {
        return $valor === null ? null : new DateTimeImmutable($valor, new DateTimeZone('UTC'));
    }
}
