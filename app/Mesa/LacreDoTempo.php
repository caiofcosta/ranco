<?php

declare(strict_types=1);

namespace App\Mesa;

/**
 * Lacre do tempo enviado pela tela do jogador (Labirinto e Tempo Certo). Não é criptografia:
 * só impede trocar o número do envio sem conhecer a conta, que a tela repete em
 * resources/js/mesa/lacre-do-tempo.js (FNV-1a de 32 bits).
 */
final class LacreDoTempo
{
    public static function de(string $jogada, int $tempo): string
    {
        return hash('fnv1a32', 'ranco|'.$tempo.'|'.$jogada);
    }

    public static function confere(string $jogada, int $tempo, mixed $lacre): bool
    {
        return is_string($lacre) && hash_equals(self::de($jogada, $tempo), $lacre);
    }
}
