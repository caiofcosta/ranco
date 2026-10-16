<?php

declare(strict_types=1);

namespace App\Dominio\Provocacao;

final readonly class Provocacao
{
    /**
     * @param int $intensidade De 1 (leve) a 3 (pesada).
     * @param list<string> $temas
     */
    public function __construct(
        public int $id,
        public string $texto,
        public int $intensidade,
        public array $temas,
        public Perspectiva $perspectiva = Perspectiva::Pessoa,
    ) {
    }

    /** @return list<string> Variáveis usadas no texto, como "nome" em "{nome}". */
    public function variaveis(): array
    {
        preg_match_all('/\{(\w+)\}/u', $this->texto, $achados);

        return array_values(array_unique($achados[1]));
    }

    /** @param array<string, string> $valores */
    public function preencher(array $valores): string
    {
        return strtr($this->texto, array_combine(
            array_map(static fn (string $nome): string => "{{$nome}}", array_keys($valores)),
            array_values($valores),
        ));
    }
}
