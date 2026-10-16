<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use App\Dominio\Mesa\SituacaoMesa;

final class PremiadosDoRanking
{
    /**
     * Colocação densa: o 2º é a próxima pontuação abaixo do 1º, e o 3º é a seguinte.
     * Pontuação zero não premia.
     *
     * @param list<array{id: string, nome: string, pontos: int}> $linhas
     * @return list<array{pessoa: string, tipo: string}>
     */
    public static function de(SituacaoMesa $situacao, array $linhas): array
    {
        if ($situacao !== SituacaoMesa::Encerrada || $linhas === []) {
            return [];
        }

        $pontuacoes = array_values(array_unique(array_column($linhas, 'pontos')));
        rsort($pontuacoes);

        $premiados = [];
        foreach (array_slice($pontuacoes, 0, 3) as $posicao => $pontos) {
            if ($pontos <= 0) {
                break;
            }

            $grupo = array_values(array_filter(
                $linhas,
                static fn (array $linha): bool => $linha['pontos'] === $pontos,
            ));
            $tipo = match ($posicao) {
                0 => count($grupo) === 1 ? 'vitoria' : 'empate',
                1 => 'segundo',
                default => 'terceiro',
            };

            foreach ($grupo as $linha) {
                $premiados[] = ['pessoa' => $linha['id'], 'tipo' => $tipo];
            }
        }

        return $premiados;
    }
}
