<?php

declare(strict_types=1);

namespace App\Dominio;

use DomainException;

final class RegraViolada extends DomainException
{
    /**
     * @param string|null $provocacao Frase de deboche que acompanha o erro, quando houver.
     * @param CodigoDaRegra|null $codigo Qual regra falhou, para a tela traduzir sem ler o texto.
     */
    public function __construct(
        string $mensagem = '',
        public readonly ?string $provocacao = null,
        public readonly ?CodigoDaRegra $codigo = null,
    ) {
        parent::__construct($mensagem);
    }
}
