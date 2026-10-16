<?php

declare(strict_types=1);

namespace App\Dominio\Provocacao;

/** Com quem a frase fala. O mesmo acontecimento pode gerar as duas mensagens. */
enum Perspectiva: string
{
    /** Fala com quem sofreu a ação ("você"): aviso na tela da própria pessoa. */
    case Pessoa = 'pessoa';

    /** Narra para todos, citando o nome: histórico da mesa. */
    case Mesa = 'mesa';
}
