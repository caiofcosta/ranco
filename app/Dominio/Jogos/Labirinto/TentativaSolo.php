<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

final class TentativaSolo
{
    /**
     * Jogada solo não concluída em 30 minutos está abandonada: não conclui mais e é apagada pela
     * limpeza agendada, sem estatística nem histórico. Uma partida leva poucos minutos.
     */
    public const VALIDADE_SEGUNDOS = 1800;

    /** @param array{linhas: list<string>, tamanho_celula: int, velocidade: int} $definicao */
    public static function validar(int $tempo, ?int $registrado, int $decorrido, array $definicao): void
    {
        if ($registrado !== null) {
            if ($registrado !== $tempo) {
                throw new FalhaSolo('Esta tentativa já foi concluída com outro tempo.', 'conflito');
            }

            return;
        }
        if ($decorrido > self::VALIDADE_SEGUNDOS) {
            throw new FalhaSolo('Esta tentativa expirou. Inicie outro jogo.', 'tempo');
        }
        if ($tempo < self::minimo($definicao) || $tempo > self::VALIDADE_SEGUNDOS * 1000 || $tempo > ($decorrido + 1) * 1000) {
            throw new FalhaSolo('O tempo não é compatível com esta tentativa.', 'tempo');
        }
    }

    public static function recorde(int $tempo, ?int $melhor): bool
    {
        return $melhor === null || $tempo < $melhor;
    }

    /** @param array{linhas: list<string>, tamanho_celula: int, velocidade: int} $definicao */
    public static function minimo(array $definicao): int
    {
        return DesenhoLabirinto::tempoMinimo($definicao);
    }
}
