<?php

declare(strict_types=1);

namespace Tests\Unit\Mesa;

use App\Mesa\LacreDoTempo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LacreDoTempoTest extends TestCase
{
    #[Test]
    public function calcula_o_mesmo_valor_da_tela(): void
    {
        // Mesmo valor conferido em tests/JavaScript/mesa/lacre-do-tempo.test.js.
        $this->assertSame('ec3a26e8', LacreDoTempo::de('comprovante-da-jogada', 12909));
    }

    #[Test]
    public function so_confere_o_lacre_do_mesmo_tempo_e_da_mesma_jogada(): void
    {
        $lacre = LacreDoTempo::de('comprovante-da-jogada', 12909);

        $this->assertTrue(LacreDoTempo::confere('comprovante-da-jogada', 12909, $lacre));
        $this->assertFalse(LacreDoTempo::confere('comprovante-da-jogada', 9000, $lacre));
        $this->assertFalse(LacreDoTempo::confere('outra-jogada', 12909, $lacre));
        $this->assertFalse(LacreDoTempo::confere('comprovante-da-jogada', 12909, null));
        $this->assertFalse(LacreDoTempo::confere('comprovante-da-jogada', 12909, ['ec3a26e8']));
    }
}
