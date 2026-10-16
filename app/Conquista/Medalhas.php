<?php

declare(strict_types=1);

namespace App\Conquista;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/** Medalhas da partida: no máximo uma por pessoa, com colocação, empate e jogo. */
final class Medalhas
{
    public static function existe(string $mesaId, int $partida, string $pessoa): bool
    {
        return DB::table('medalhas')
            ->where('mesa_id', $mesaId)
            ->where('partida', $partida)
            ->where('pessoa', $pessoa)
            ->exists();
    }

    /**
     * Pares partida/pessoa já gravados nesta mesa, numa consulta só.
     *
     * @return array<int, array<string, true>>
     */
    public static function concedidasNaMesa(string $mesaId): array
    {
        $pares = [];
        foreach (DB::table('medalhas')->where('mesa_id', $mesaId)->get(['partida', 'pessoa']) as $linha) {
            $pares[(int) $linha->partida][(string) $linha->pessoa] = true;
        }

        return $pares;
    }

    /**
     * Medalhas gravadas numa partida, por pessoa, numa consulta só.
     *
     * @return array<string, array{colocacao: int, empate: bool}>
     */
    public static function daPartida(string $mesaId, int $partida): array
    {
        $medalhas = [];
        foreach (DB::table('medalhas')->where('mesa_id', $mesaId)->where('partida', $partida)->get(['pessoa', 'colocacao', 'empate']) as $linha) {
            $medalhas[(string) $linha->pessoa] = ['colocacao' => (int) $linha->colocacao, 'empate' => (bool) $linha->empate];
        }

        return $medalhas;
    }

    /**
     * Imagem em public/images/medalhas e o texto alternativo da medalha.
     *
     * @return array{arquivo: string, descricao: string}
     */
    public static function imagem(int $colocacao, bool $empate): array
    {
        $cor = [1 => 'ouro', 2 => 'prata', 3 => 'bronze'][$colocacao];

        return [
            'arquivo' => 'images/medalhas/medalha-'.($empate ? 'dupla-' : '').$cor.'.png',
            'descricao' => $empate
                ? "Medalha dupla de {$cor}, empate em {$colocacao}º lugar"
                : "Medalha de {$cor}, {$colocacao}º lugar",
        ];
    }

    public static function frase(string $mesaId, int $partida): ?string
    {
        $frase = DB::table('medalhas')
            ->where('mesa_id', $mesaId)
            ->where('partida', $partida)
            ->where('colocacao', 1)
            ->orderBy('id')
            ->value('frase');

        return is_string($frase) && $frase !== '' ? $frase : null;
    }

    public static function conceder(
        string $mesaId,
        int $partida,
        string $pessoa,
        int $colocacao,
        bool $empate,
        string $jogo,
        ?string $frase,
        string $nomeDaMesa,
        ?DateTimeImmutable $partidaCriadaEm = null,
    ): void {
        DB::table('medalhas')->insertOrIgnore([
            'pessoa' => $pessoa,
            'mesa_id' => $mesaId,
            'mesa_nome' => $nomeDaMesa,
            'partida' => $partida,
            'colocacao' => $colocacao,
            'empate' => $empate,
            'jogo' => $jogo,
            'frase' => $frase,
            'concedida_em' => now(),
            'partida_criada_em' => self::emUtc($partidaCriadaEm),
        ]);
    }

    public static function quantidade(string $mesaId): int
    {
        return DB::table('medalhas')->where('mesa_id', $mesaId)->count();
    }

    private static function emUtc(?DateTimeImmutable $data): ?string
    {
        return $data?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
