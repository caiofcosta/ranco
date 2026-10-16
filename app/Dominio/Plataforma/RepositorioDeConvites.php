<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

use App\Dominio\Social\Convite;

interface RepositorioDeConvites
{
    public function novoId(): string;

    public function buscar(string $id): ?Convite;

    public function salvar(Convite $convite): void;

    /** @return list<Convite> */
    public function pendentesDaMesa(string $mesaId): array;

    /** @return list<Convite> Pendentes em qualquer direção entre as duas pessoas. */
    public function pendentesEntre(string $a, string $b): array;

    /** @return list<Convite> */
    public function pendentesPara(string $usuario): array;
}
