<?php

declare(strict_types=1);

namespace App\Opcoes;

/**
 * Listas da tabela `opcoes`: textos simples que a tela mostra ou oferece como escolha, editados no
 * Painel. O que tem regra ou relação própria (provocações, jogos) fica nas tabelas próprias.
 */
enum Lista: string
{
    case NomesDeMesa = 'nomes_de_mesa';

    public function rotulo(): string
    {
        return match ($this) {
            self::NomesDeMesa => 'Sugestões de nome de mesa',
        };
    }
}
