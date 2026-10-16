<?php

declare(strict_types=1);

namespace App\Acesso;

/**
 * Limites de tentativas com pausa (decisão do projeto): atingido o limite dentro da janela, a
 * pessoa espera a pausa e depois pode tentar de novo. Não existe bloqueio definitivo.
 */
enum TipoDeLimite: string
{
    case LoginPorEmail = 'login_por_email';
    case LoginPorOrigem = 'login_por_origem';
    case EmailsPorEmail = 'emails_por_email';
    case EmailsPorOrigem = 'emails_por_origem';

    public function limite(): int
    {
        return match ($this) {
            self::LoginPorEmail => 5,
            self::LoginPorOrigem => 20,
            self::EmailsPorEmail => 3,
            self::EmailsPorOrigem => 10,
        };
    }

    /** Período em que as tentativas são contadas. */
    public function janelaEmSegundos(): int
    {
        return match ($this) {
            self::EmailsPorOrigem => 60 * 60,
            default => 15 * 60,
        };
    }

    /** Espera a partir do momento em que o limite é atingido. */
    public function pausaEmSegundos(): int
    {
        return match ($this) {
            self::EmailsPorOrigem => 60 * 60,
            default => 15 * 60,
        };
    }
}
