<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use DateTimeImmutable;
use stdClass;

interface RepositorioSoloLabirinto
{
    /** @return list<stdClass> */
    public function disponiveis(int $pessoa): array;

    public function versaoDisponivel(?int $mapa): ?int;

    public function criar(int $pessoa, int $versao, DateTimeImmutable $agora): string;

    public function buscar(int $pessoa, string $id): stdClass;

    public function travar(int $pessoa, string $id): void;

    public function melhor(int $pessoa, int $versao): ?int;

    public function registrar(string $id, int $tempo, bool $recorde, DateTimeImmutable $agora): void;

    /** @return array{melhorTempo: int|null, melhores: list<stdClass>, ultimas: list<stdClass>} */
    public function historico(int $pessoa, int $versao): array;

    public function removerAbertasAntesDe(DateTimeImmutable $limite): int;
}
