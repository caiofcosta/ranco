<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;

/**
 * Soma o ranking da mesa a partir das partidas já concluídas com vencedor.
 * A colocação final é densa, inclusive no 1º: 1º vale 3, 2º vale 2, do 3º em diante vale 1.
 * Quem empata recebe a pontuação da posição.
 */
final readonly class RankingDaMesa
{
    /**
     * @param  list<array{id: string, nome: string, pontos: int}>  $linhas
     */
    public function __construct(public array $linhas) {}

    public static function de(Mesa $mesa): self
    {
        $linhas = [];
        foreach (self::pontos($mesa) as $id => $pontos) {
            $id = (string) $id;
            $nome = PessoasDaMesa::nome($mesa, $id);
            if ($nome === null) {
                continue;
            }
            $linhas[] = ['id' => $id, 'nome' => $nome, 'pontos' => $pontos];
        }

        usort($linhas, static fn (array $a, array $b): int => $b['pontos'] <=> $a['pontos'] ?: strcmp($a['nome'], $b['nome']));

        return new self($linhas);
    }

    /** @return array<string, int> */
    public static function pontos(Mesa $mesa): array
    {
        $totais = [];
        foreach ($mesa->partidas() as $partida) {
            foreach ($partida->classificacao() as $linha) {
                $pontos = match ($linha['colocacao']) {
                    1 => 3,
                    2 => 2,
                    default => 1,
                };
                $totais[$linha['pessoa']] = ($totais[$linha['pessoa']] ?? 0) + $pontos;
            }
        }

        return $totais;
    }
}
