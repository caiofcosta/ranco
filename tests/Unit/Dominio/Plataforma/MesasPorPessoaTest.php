<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Plataforma;

use App\Dominio\CodigoDaRegra;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Infra\Memoria\PlataformaEmMemoria;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class MesasPorPessoaTest extends TestCase
{
    private Plataforma $plataforma;

    protected function setUp(): void
    {
        $this->plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-04 10:00:00')));
    }

    #[Test]
    public function aPessoaCriaUmaMesaEEntraEmOutraAoMesmoTempo(): void
    {
        $daAna = $this->plataforma->criarMesa('ana');
        $this->plataforma->aceitarConviteDeAmizade('bruno', 'ana');
        $doBruno = $this->plataforma->criarMesa('bruno');
        $convite = $this->plataforma->convidar($doBruno->id, 'bruno', 'ana');

        $this->plataforma->aceitarConvite($convite->id, 'ana');

        $ids = array_map(static fn (Mesa $mesa): string => $mesa->id, $this->plataforma->mesasAbertasDe('ana'));
        $this->assertEqualsCanonicalizing([$daAna->id, $doBruno->id], $ids);
        // Só a mesa que a Ana administra consome vaga; a da Bruno, em que ela participa, não.
        $this->assertSame(Plataforma::LIMITE_DE_MESAS - 1, $this->plataforma->vagasDeMesa('ana'));
    }

    #[Test]
    public function sairDeUmaMesaLiberaVaga(): void
    {
        $mesas = [];
        for ($i = 0; $i < Plataforma::LIMITE_DE_MESAS; $i++) {
            $mesas[] = $this->plataforma->criarMesa('ana');
        }

        $this->plataforma->encerrarMesa($mesas[0]->id, 'ana');

        $this->assertSame(1, $this->plataforma->vagasDeMesa('ana'));
        $this->plataforma->criarMesa('ana');
        $this->assertSame(0, $this->plataforma->vagasDeMesa('ana'));
    }

    #[Test]
    public function soJogaEmUmaMesaPorVez(): void
    {
        [$mesaA, $mesaB] = $this->duasMesasEmJogo();

        $this->iniciar($mesaA->id, 'ana');

        $erro = $this->regra(fn () => $this->iniciar($mesaB->id, 'ana'));
        $this->assertSame(CodigoDaRegra::JogadaEmOutraMesa, $erro->codigo);
        $this->assertTrue($this->plataforma->temJogadaEmAndamento('ana'));

        $this->plataforma->naMesa($mesaA->id, static fn (Mesa $mesa) => $mesa->partidaAtiva()->concluirTentativa('ana', 9000));
        $this->assertFalse($this->plataforma->temJogadaEmAndamento('ana'));
        $this->iniciar($mesaB->id, 'ana');
        $this->assertTrue($this->plataforma->temJogadaEmAndamento('ana'));
    }

    #[Test]
    public function repetirOInicioNaMesmaMesaNaoEhOutraJogada(): void
    {
        [$mesaA] = $this->duasMesasEmJogo();

        $this->iniciar($mesaA->id, 'ana');
        $this->iniciar($mesaA->id, 'ana');

        $this->assertTrue($this->plataforma->temJogadaEmAndamento('ana'));
    }

    #[Test]
    public function adminJogandoPelaVezDeUmLocalTambemEstaSentado(): void
    {
        $mesaA = $this->plataforma->criarMesa('ana');
        $local = $this->plataforma->cadastrarParticipanteLocal($mesaA->id, 'Zeca', 'zeca@exemplo.test', 'ana');
        $this->plataforma->criarPartida($mesaA->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), 'ana');
        $this->plataforma->iniciarPartida($mesaA->id, 'ana');
        $vez = $this->plataforma->mesa($mesaA->id)->partidaAtiva()->vezAtual();
        if ($vez === 'ana') {
            $this->plataforma->naMesa($mesaA->id, static function (Mesa $mesa): void {
                $mesa->partidaAtiva()->iniciarTentativa('ana');
                $mesa->partidaAtiva()->concluirTentativa('ana', 9000);
            });
        }
        $this->assertSame($local, $this->plataforma->mesa($mesaA->id)->partidaAtiva()->vezAtual());
        $this->plataforma->iniciarTentativa($mesaA->id, 'ana', static fn (Mesa $m): array => [$m->partidaAtiva(), $local]);

        $mesaB = $this->mesaEmJogoDoBrunoComAna();

        $erro = $this->regra(fn () => $this->iniciar($mesaB->id, 'ana'));
        $this->assertSame(CodigoDaRegra::JogadaEmOutraMesa, $erro->codigo);
    }

    #[Test]
    public function invisivelNaoIniciaJogada(): void
    {
        [$mesaA] = $this->duasMesasEmJogo();
        $this->plataforma->ficarInvisivel('ana');

        $erro = $this->regra(fn () => $this->iniciar($mesaA->id, 'ana'));
        $this->assertSame(CodigoDaRegra::Invisivel, $erro->codigo);
    }

    /** @return array{Mesa, Mesa} Duas mesas em jogo no paralelo, com a Ana nas duas. */
    private function duasMesasEmJogo(): array
    {
        $mesaA = $this->plataforma->criarMesa('ana');
        $this->plataforma->aceitarConviteDeAmizade('ana', 'carla');
        $convite = $this->plataforma->convidar($mesaA->id, 'ana', 'carla');
        $this->plataforma->aceitarConvite($convite->id, 'carla');
        $this->plataforma->criarPartida($mesaA->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo), 'ana');
        $this->plataforma->iniciarPartida($mesaA->id, 'ana');

        return [$mesaA, $this->mesaEmJogoDoBrunoComAna()];
    }

    private function mesaEmJogoDoBrunoComAna(): Mesa
    {
        $mesa = $this->plataforma->criarMesa('bruno');
        $this->plataforma->aceitarConviteDeAmizade('bruno', 'ana');
        $convite = $this->plataforma->convidar($mesa->id, 'bruno', 'ana');
        $this->plataforma->aceitarConvite($convite->id, 'ana');
        $this->plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo), 'bruno');
        $this->plataforma->iniciarPartida($mesa->id, 'bruno');

        return $mesa;
    }

    private function iniciar(string $mesaId, string $pessoa): void
    {
        $this->plataforma->iniciarTentativa($mesaId, $pessoa, static fn (Mesa $m): array => [$m->partidaAtiva(), $pessoa]);
    }

    private function regra(callable $acao): RegraViolada
    {
        try {
            $acao();
        } catch (RegraViolada $erro) {
            return $erro;
        }
        $this->fail('Esperava RegraViolada.');
    }
}
