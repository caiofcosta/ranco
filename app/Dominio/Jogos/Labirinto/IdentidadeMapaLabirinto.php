<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use App\Dominio\RegraViolada;

final class IdentidadeMapaLabirinto
{
    public static function nome(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '' || mb_strlen($nome) > 80) {
            throw new RegraViolada('Informe um nome com até 80 caracteres.');
        }

        return $nome;
    }

    public static function chaveNome(string $nome): string
    {
        return hash('sha256', mb_strtolower(self::nome($nome), 'UTF-8'));
    }

    /** @param list<string> $linhas */
    public static function chaveDesenho(array $linhas): string
    {
        DesenhoLabirinto::validarFormato($linhas);

        return hash('sha256', implode("\n", $linhas));
    }
}
