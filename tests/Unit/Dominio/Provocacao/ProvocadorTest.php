<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Provocacao;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocacao;
use App\Dominio\Provocacao\Provocador;
use App\Infra\Memoria\RepositorioDeProvocacoesEmMemoria;

final class ProvocadorTest extends TestCase
{
    #[Test]
    public function aMesmaFraseServeEmVariosMomentosPeloTema(): void
    {
        $provocador = $this->provocador(
            [new Provocacao(1, 'O universo está te dando uma dica.', 1, ['azar'])],
            [Momento::ErroDeLogin->value => ['azar'], Momento::TentativaInterrompida->value => ['azar']],
        );

        $this->assertSame('O universo está te dando uma dica.', $provocador->sortear(Momento::ErroDeLogin));
        $this->assertSame('O universo está te dando uma dica.', $provocador->sortear(Momento::TentativaInterrompida, variaveis: ['nome' => 'Ana']));
    }

    #[Test]
    public function preencheONomeDoParticipante(): void
    {
        $provocador = $this->provocador(
            [new Provocacao(1, '{nome} dormiu no ponto e o prazo venceu.', 1, ['lentidao'], Perspectiva::Mesa)],
            [Momento::VezPuladaPorPrazo->value => ['lentidao']],
        );

        $this->assertSame(
            'Bruno dormiu no ponto e o prazo venceu.',
            $provocador->sortear(Momento::VezPuladaPorPrazo, variaveis: ['nome' => 'Bruno'], perspectiva: Perspectiva::Mesa),
        );
    }

    #[Test]
    public function fraseComVariavelQueOMomentoNaoTemNaoESorteada(): void
    {
        $provocador = $this->provocador(
            [
                new Provocacao(1, '{nome} caiu do sistema.', 1, ['azar']),
                new Provocacao(2, 'O universo está te dando uma dica.', 1, ['azar']),
            ],
            [Momento::ErroDeLogin->value => ['azar']],
        );

        for ($vez = 0; $vez < 20; $vez++) {
            $this->assertSame('O universo está te dando uma dica.', $provocador->sortear(Momento::ErroDeLogin));
        }
    }

    #[Test]
    public function prefereAIntensidadeMaisProximaDaPedida(): void
    {
        $provocador = $this->provocador(
            [
                new Provocacao(1, 'Leve.', 1, ['azar']),
                new Provocacao(2, 'Pesada.', 3, ['azar']),
            ],
            [Momento::ErroDeLogin->value => ['azar']],
        );

        $this->assertSame('Leve.', $provocador->sortear(Momento::ErroDeLogin, 1));
        $this->assertSame('Pesada.', $provocador->sortear(Momento::ErroDeLogin, 3));
        $this->assertContains($provocador->sortear(Momento::ErroDeLogin, 2), ['Leve.', 'Pesada.'], 'Empate na distância: as duas entram no sorteio.');
    }

    #[Test]
    public function sorteiaEntreAsFrasesParaNaoRepetirSempre(): void
    {
        $provocador = $this->provocador(
            [new Provocacao(1, 'A.', 1, ['azar']), new Provocacao(2, 'B.', 1, ['azar']), new Provocacao(3, 'C.', 1, ['azar'])],
            [Momento::ErroDeLogin->value => ['azar']],
            new Randomizer(new Mt19937(2026)),
        );

        $sorteadas = [];
        for ($vez = 0; $vez < 30; $vez++) {
            $sorteadas[] = $provocador->sortear(Momento::ErroDeLogin);
        }

        $this->assertSame(['A.', 'B.', 'C.'], array_values(array_unique(array_merge(['A.', 'B.', 'C.'], $sorteadas))));
        $this->assertGreaterThan(1, count(array_unique($sorteadas)));
    }

    #[Test]
    public function oMesmoAcontecimentoFalaComAPessoaENarraParaAMesa(): void
    {
        $provocador = $this->provocador(
            [
                new Provocacao(1, 'Tecnicamente você participou.', 2, ['derrota']),
                new Provocacao(2, '{nome} ficou para trás.', 2, ['derrota'], Perspectiva::Mesa),
            ],
            [Momento::Derrota->value => ['derrota']],
        );

        $this->assertSame('Tecnicamente você participou.', $provocador->sortear(Momento::Derrota, 2, ['nome' => 'Ana']));
        $this->assertSame('Ana ficou para trás.', $provocador->sortear(Momento::Derrota, 2, ['nome' => 'Ana'], Perspectiva::Mesa));
    }

    #[Test]
    public function semFraseParaOMomentoNaoHaProvocacao(): void
    {
        $provocador = $this->provocador([new Provocacao(1, 'A.', 1, ['vitoria'])], [Momento::ErroDeLogin->value => ['azar']]);

        $this->assertNull($provocador->sortear(Momento::ErroDeLogin));
    }

    /**
     * @param list<Provocacao> $frases
     * @param array<string, list<string>> $temasPorMomento
     */
    private function provocador(array $frases, array $temasPorMomento, ?Randomizer $sorteio = null): Provocador
    {
        return new Provocador(new RepositorioDeProvocacoesEmMemoria($frases, $temasPorMomento), $sorteio);
    }
}
