<?php

declare(strict_types=1);

namespace App\Dominio\Provocacao;

interface RepositorioDeProvocacoes
{
    /** @return list<Provocacao> Frases ativas com pelo menos um tema aceito pelo momento. */
    public function candidatasPara(Momento $momento): array;
}
