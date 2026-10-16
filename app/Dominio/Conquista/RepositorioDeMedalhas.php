<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use DateTimeImmutable;

interface RepositorioDeMedalhas
{
    /**
     * @param  list<string>  $pessoas
     * @return list<array{id: int, colocacao: int, empate: bool, jogo: string, mesa: string, frase: ?string, concedidaEm: DateTimeImmutable, partidaCriadaEm: ?DateTimeImmutable}>
     * Da data mostrada mais recente para a mais antiga, com desempate pelo id.
     */
    public function dasPessoas(array $pessoas): array;
}
