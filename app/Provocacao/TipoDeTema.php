<?php

declare(strict_types=1);

namespace App\Provocacao;

/** Tipo do tema na tabela `temas_de_provocacao`. */
enum TipoDeTema: string
{
    case Generico = 'generico';
    case Contexto = 'contexto';

    public function rotulo(): string
    {
        return match ($this) {
            self::Generico => 'Genérico',
            self::Contexto => 'Contexto',
        };
    }
}
