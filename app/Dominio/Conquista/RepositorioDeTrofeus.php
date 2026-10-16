<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use DateTimeImmutable;

interface RepositorioDeTrofeus
{
    /**
     * Da data mostrada mais recente para a mais antiga, com desempate pelo id.
     * @param list<string> $pessoas
     * @return list<array{id: int, pessoa: string, tipo: string, mesa: string, frase: ?string, concedidoEm: DateTimeImmutable, mesaCriadaEm: ?DateTimeImmutable}>
     */
    public function dasPessoas(array $pessoas): array;

    /** @return list<array{id: int, pessoa: string, tipo: string, mesa: string, frase: ?string, concedidoEm: DateTimeImmutable, mesaCriadaEm: ?DateTimeImmutable}> */
    public function daMesa(string $mesaId): array;

    /**
     * @param list<array{pessoa: string, tipo: string, frase?: ?string}> $premiados
     */
    public function conceder(
        string $mesaId,
        string $nomeDaMesa,
        array $premiados,
        DateTimeImmutable $quando,
        ?DateTimeImmutable $mesaCriadaEm = null,
    ): void;
}
