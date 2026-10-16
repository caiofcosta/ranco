<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;

/**
 * Classificação da mesa para o rodapé do celular: os membros ativos (até os lugares da mesa) com os
 * pontos já somados por RankingDaMesa. Só compõe dados existentes — não pontua nada por conta
 * própria. A ordem e a posição densa seguem a mesma regra do ranking (pontos desc, depois nome);
 * o 1º lugar (inclusive no empate) fica marcado quando já houve resultado.
 */
final readonly class ClassificacaoDaMesa
{
    /**
     * @param  list<array{id: string, nome: string, compacto: string, pontos: int, posicao: int, primeiro: bool}>  $linhas
     */
    public function __construct(public array $linhas, public bool $temResultado) {}

    public static function de(Mesa $mesa): self
    {
        $pontos = RankingDaMesa::pontos($mesa);
        $temResultado = $pontos !== [];

        $linhas = [];
        foreach (PessoasDaMesa::de($mesa) as $pessoa) {
            $linhas[] = [
                'id' => $pessoa['id'],
                'nome' => $pessoa['nome'],
                'compacto' => self::compacto($pessoa['nome']),
                'pontos' => $pontos[$pessoa['id']] ?? 0,
            ];
        }

        usort($linhas, static fn (array $a, array $b): int => $b['pontos'] <=> $a['pontos'] ?: strcmp($a['nome'], $b['nome']));

        $posicao = 0;
        $pontosAnteriores = null;
        foreach ($linhas as $indice => $linha) {
            if ($linha['pontos'] !== $pontosAnteriores) {
                $posicao++;
                $pontosAnteriores = $linha['pontos'];
            }
            $linhas[$indice]['posicao'] = $posicao;
            $linhas[$indice]['primeiro'] = $temResultado && $posicao === 1;
        }

        return new self($linhas, $temResultado);
    }

    /** O primeiro nome, para caber no cartão estreito; o nome inteiro fica no title. */
    private static function compacto(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [''];

        return $partes[0] === '' ? $nome : $partes[0];
    }
}
