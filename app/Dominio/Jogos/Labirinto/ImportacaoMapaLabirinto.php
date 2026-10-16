<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use App\Dominio\RegraViolada;
use JsonException;
use stdClass;

final class ImportacaoMapaLabirinto
{
    public const LIMITE_BYTES = 8192;

    /** @return array{nome: string, linhas: list<string>} */
    public static function ler(string $json): array
    {
        if (strlen($json) > self::LIMITE_BYTES) {
            throw new RegraViolada('O JSON deve ter no máximo 8 KB.');
        }
        try {
            $dados = json_decode($json, false, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RegraViolada('JSON inválido. Cole apenas o objeto JSON, sem explicações ou blocos de Markdown.');
        }
        if (! $dados instanceof stdClass
            || array_diff(array_keys(get_object_vars($dados)), ['formato', 'nome', 'linhas']) !== []
            || ($dados->formato ?? null) !== 1
            || ! is_string($dados->nome ?? null)
            || ! is_array($dados->linhas ?? null)) {
            throw new RegraViolada('Use somente formato (1), nome (texto) e linhas (lista de 13 textos).');
        }
        $nome = IdentidadeMapaLabirinto::nome($dados->nome);
        DesenhoLabirinto::validarFormato($dados->linhas);
        /** @var list<string> $linhas */
        $linhas = $dados->linhas;
        DesenhoLabirinto::validarPublicacao($linhas);

        return ['nome' => $nome, 'linhas' => $linhas];
    }
}
