<?php

declare(strict_types=1);

namespace App\Dominio\Provocacao;

use Random\Randomizer;

/**
 * Sorteia a provocação de um momento: só entram frases da perspectiva pedida e cujas variáveis
 * o momento fornece; entre elas, ficam as de intensidade mais próxima da pedida, e uma é sorteada.
 */
final class Provocador
{
    private readonly Randomizer $sorteio;

    public function __construct(
        private readonly RepositorioDeProvocacoes $provocacoes,
        ?Randomizer $sorteio = null,
    ) {
        $this->sorteio = $sorteio ?? new Randomizer();
    }

    /** @param array<string, string|int> $variaveis */
    public function sortear(
        Momento $momento,
        int $intensidade = 1,
        array $variaveis = [],
        Perspectiva $perspectiva = Perspectiva::Pessoa,
    ): ?string {
        $valores = array_map('strval', $variaveis);
        $possiveis = array_values(array_filter(
            $this->provocacoes->candidatasPara($momento),
            static fn (Provocacao $p): bool => $p->perspectiva === $perspectiva
                && array_diff($p->variaveis(), array_keys($valores)) === [],
        ));
        if ($possiveis === []) {
            return null;
        }

        $menorDistancia = min(array_map(static fn (Provocacao $p): int => abs($p->intensidade - $intensidade), $possiveis));
        $maisProximas = array_values(array_filter(
            $possiveis,
            static fn (Provocacao $p): bool => abs($p->intensidade - $intensidade) === $menorDistancia,
        ));

        return $maisProximas[$this->sorteio->getInt(0, count($maisProximas) - 1)]->preencher($valores);
    }
}
