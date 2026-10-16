<?php

declare(strict_types=1);

namespace Tests\Unit\Convite;

use App\Convite\MensagemDoConvite;
use App\Dominio\CodigoDaRegra;
use App\Dominio\RegraViolada;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MensagemDoConviteTest extends TestCase
{
    #[Test]
    public function traduzPeloCodigoMesmoQueOTextoDoDominioMude(): void
    {
        $erro = new RegraViolada('qualquer texto novo', null, CodigoDaRegra::JaEstaNaMesa);

        $this->assertSame('Essa pessoa já está na mesa.', MensagemDoConvite::de($erro));
    }

    #[Test]
    public function bloqueioNaoERevelado(): void
    {
        $erro = new RegraViolada('Convite não permitido: a pessoa bloqueou você.', null, CodigoDaRegra::ConvidadoBloqueou);

        $this->assertSame('Este convite não está mais pendente.', MensagemDoConvite::de($erro));
    }

    #[Test]
    public function limiteDeMesasUsaAFraseDoDominio(): void
    {
        $erro = new RegraViolada('Você já está em 5 mesas, o máximo.', null, CodigoDaRegra::LimiteDeMesas);

        $this->assertSame('Você já está em 5 mesas, o máximo.', MensagemDoConvite::de($erro));
    }
}
