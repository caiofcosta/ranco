<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use DateTimeImmutable;

interface RepositorioDeRevelacoes
{
    /** @return list<array{id: string, nome: string}> */
    public function pendentesDe(string $usuario): array;

    public function marcarVista(string $usuario, string $mesaId, DateTimeImmutable $quando): void;
}
