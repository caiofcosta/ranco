<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use App\Dominio\RegraViolada;

/**
 * Versões e desenhos congelados; o sorteio faz parte do estado transacional da partida.
 *
 * @phpstan-type Mapa array{id: int, mapa_id: int, nome: string, definicao: array{formato: int, linhas: list<string>, tamanho_celula: int, raio_bolinha: int, velocidade: int}}
 */
final class MapasDaPartida
{
    /** @var list<int> */
    private array $restantes = [];

    /** @var array<int, int> Rodada => índice no conjunto congelado. */
    private array $sorteados = [];

    /** @param list<Mapa> $versoes */
    public function __construct(private array $versoes)
    {
        if ($versoes === []) {
            throw new RegraViolada('Publique e ative ao menos um mapa de Labirinto antes de iniciar a partida.');
        }
    }

    public function sortear(int $rodada): void
    {
        if (isset($this->sorteados[$rodada])) {
            return;
        }
        $anterior = $this->sorteados[$rodada - 1] ?? null;
        if ($this->restantes === []) {
            $this->restantes = array_keys($this->versoes);
        }
        $candidatos = $this->restantes;
        if (count($candidatos) > 1 && $anterior !== null) {
            $candidatos = array_values(array_diff($candidatos, [$anterior]));
        }
        $indice = $candidatos[random_int(0, count($candidatos) - 1)];
        $this->sorteados[$rodada] = $indice;
        $this->restantes = array_values(array_diff($this->restantes, [$indice]));
    }

    /** @return Mapa|null */
    public function daRodada(int $rodada): ?array
    {
        return isset($this->sorteados[$rodada]) ? $this->versoes[$this->sorteados[$rodada]] : null;
    }
}
