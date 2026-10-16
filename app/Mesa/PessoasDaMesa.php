<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Models\User;

/** Nomes e iniciais das pessoas da mesa, nunca o identificador. */
final class PessoasDaMesa
{
    /**
     * @return list<array{id: string, nome: string, iniciais: string, local: bool}>
     */
    public static function de(Mesa $mesa): array
    {
        self::preparar($mesa);
        $pessoas = [];
        foreach ($mesa->membrosAtivos() as $id) {
            $nome = self::nome($mesa, $id);
            if ($nome === null) {
                continue;
            }
            $pessoas[] = [
                'id' => $id,
                'nome' => $nome,
                'iniciais' => self::iniciais($nome),
                'local' => $mesa->participanteLocal($id) !== null,
            ];
        }

        return $pessoas;
    }

    /** Uma consulta para os nomes das contas da mesa, reaproveitada no resto da requisição. */
    public static function preparar(Mesa $mesa): void
    {
        $cache = app(NomesDaMesaNaRequisicao::class);
        if ($cache->lembrar($mesa->id) === null) {
            $cache->guardar($mesa->id, self::carregar($mesa));
        }
    }

    public static function nome(Mesa $mesa, string $id): ?string
    {
        $cache = app(NomesDaMesaNaRequisicao::class);
        $mapa = $cache->lembrar($mesa->id);
        if ($mapa === null) {
            $mapa = self::carregar($mesa);
            $cache->guardar($mesa->id, $mapa);
        }
        if (! array_key_exists($id, $mapa)) {
            $mapa[$id] = self::resolver($mesa, $id, User::query()->find($id)?->name);
            $cache->guardar($mesa->id, $mapa);
        }

        return $mapa[$id];
    }

    public static function iniciais(string $nome): string
    {
        return mb_strtoupper(mb_substr(trim($nome), 0, 1));
    }

    /**
     * Duas pessoas: "Ana e Bruno". Da terceira em diante, vírgula e "e" só antes da última.
     *
     * @param list<string> $nomes
     */
    public static function juntar(array $nomes): string
    {
        $nomes = array_values($nomes);
        $quantidade = count($nomes);
        if ($quantidade <= 1) {
            return $nomes[0] ?? '';
        }
        if ($quantidade === 2) {
            return $nomes[0].' e '.$nomes[1];
        }

        $ultimo = array_pop($nomes);

        return implode(', ', $nomes).' e '.$ultimo;
    }

    /** @return array<string, ?string> */
    private static function carregar(Mesa $mesa): array
    {
        $ids = [];
        foreach ($mesa->membros() as $membro) {
            $ids[(string) $membro['usuario']] = true;
        }
        foreach ($mesa->partidas() as $partida) {
            foreach ($partida->ordem() as $id) {
                $ids[(string) $id] = true;
            }
        }

        $contas = [];
        foreach (array_keys($ids) as $id) {
            $id = (string) $id;
            if ($mesa->participanteLocal($id) === null) {
                $contas[] = $id;
            }
        }

        $nomesDeConta = [];
        if ($contas !== []) {
            foreach (User::query()->whereIn('id', $contas)->get(['id', 'name']) as $usuario) {
                $nomesDeConta[(string) $usuario->id] = $usuario->name;
            }
        }

        $mapa = [];
        foreach (array_keys($ids) as $id) {
            $id = (string) $id;
            $mapa[$id] = self::resolver($mesa, $id, $nomesDeConta[$id] ?? null);
        }

        return $mapa;
    }

    private static function resolver(Mesa $mesa, string $id, mixed $nome): ?string
    {
        if (is_string($nome) && $nome !== '') {
            return $nome;
        }

        $local = $mesa->participanteLocal($id)?->nome;

        return is_string($local) && $local !== '' ? $local : null;
    }
}
