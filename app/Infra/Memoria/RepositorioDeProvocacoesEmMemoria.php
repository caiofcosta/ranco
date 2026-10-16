<?php

declare(strict_types=1);

namespace App\Infra\Memoria;

use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocacao;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;

final class RepositorioDeProvocacoesEmMemoria implements RepositorioDeProvocacoes
{
    /**
     * @param list<Provocacao> $provocacoes
     * @param array<string, list<string>> $temasPorMomento Código do momento => temas aceitos.
     */
    public function __construct(
        private readonly array $provocacoes,
        private readonly array $temasPorMomento,
    ) {
    }

    public function candidatasPara(Momento $momento): array
    {
        $aceitos = $this->temasPorMomento[$momento->value] ?? [];

        return array_values(array_filter(
            $this->provocacoes,
            static fn (Provocacao $p): bool => array_intersect($p->temas, $aceitos) !== [],
        ));
    }
}
