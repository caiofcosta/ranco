<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

/** @phpstan-import-type Mapa from MapasDaPartida */
interface MapasDisponiveisParaPartida
{
    /** @return list<Mapa> Última versão publicada de cada mapa ativo. */
    public function listar(): array;
}
