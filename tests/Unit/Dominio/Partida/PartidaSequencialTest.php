<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Partida;

use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\Origem;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Partida\TipoEvento;
use App\Dominio\RegraViolada;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class PartidaSequencialTest extends TestCase
{
    private const PRAZO = 300;

    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-01 10:00:00'));
    }

    // Início e ordem

    #[Test]
    public function partida_nasce_em_configuracao_com_admin_como_primeiro_da_ordem(): void
    {
        $partida = $this->novaPartida();

        $this->assertSame(SituacaoPartida::EmConfiguracao, $partida->situacao());
        $this->assertSame(['ana'], $partida->ordem());
    }

    #[Test]
    public function ordem_inicial_segue_a_ordem_de_entrada(): void
    {
        $partida = $this->novaPartida('bruno', 'carla');

        $this->assertSame(['ana', 'bruno', 'carla'], $partida->ordem());
    }

    #[Test]
    public function admin_reordena_antes_de_iniciar(): void
    {
        $partida = $this->novaPartida('bruno', 'carla');
        $partida->reordenar(['carla', 'ana', 'bruno'], por: 'ana');

        $this->assertSame(['carla', 'ana', 'bruno'], $partida->ordem());
    }

    #[Test]
    public function somente_admin_reordena(): void
    {
        $partida = $this->novaPartida('bruno', 'carla');

        $this->expectException(RegraViolada::class);
        $partida->reordenar(['carla', 'ana', 'bruno'], por: 'bruno');
    }

    #[Test]
    public function nao_inicia_com_menos_que_o_minimo_de_jogadores(): void
    {
        $partida = $this->novaPartida();

        $this->expectException(RegraViolada::class);
        $partida->iniciar(por: 'ana');
    }

    #[Test]
    public function somente_admin_inicia_a_partida(): void
    {
        $partida = $this->novaPartida('bruno');

        $this->expectException(RegraViolada::class);
        $partida->iniciar(por: 'bruno');
    }

    #[Test]
    public function ordem_trava_depois_do_inicio(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->reordenar(['carla', 'bruno', 'ana'], por: 'ana');
    }

    #[Test]
    public function iniciar_abre_a_primeira_rodada_na_vez_do_primeiro_da_ordem(): void
    {
        $partida = $this->partidaIniciada();

        $this->assertSame(SituacaoPartida::EmAndamento, $partida->situacao());
        $this->assertSame(1, $partida->rodadaAtual());
        $this->assertSame('ana', $partida->vezAtual());
        $this->assertEventoRegistrado($partida, TipoEvento::PartidaIniciada, Origem::Admin);
    }

    // Tentativa

    #[Test]
    public function so_quem_esta_na_vez_inicia_tentativa(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->iniciarTentativa('bruno');
    }

    #[Test]
    public function concluir_tentativa_passa_a_vez_para_o_proximo(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');
        $partida->concluirTentativa('ana', resultado: 42);

        $tentativa = $partida->tentativaDe(1, 'ana');
        $this->assertSame(SituacaoTentativa::Concluida, $tentativa?->situacao);
        $this->assertSame(42, $tentativa?->resultado);
        $this->assertSame('bruno', $partida->vezAtual());
    }

    #[Test]
    public function depois_da_ultima_vez_abre_nova_rodada(): void
    {
        $partida = $this->partidaIniciada();
        foreach (['ana' => 10, 'bruno' => 9, 'carla' => 8] as $jogador => $resultado) {
            $partida->iniciarTentativa($jogador);
            $partida->concluirTentativa($jogador, resultado: $resultado);
        }

        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame('ana', $partida->vezAtual());
    }

    #[Test]
    public function interrupcao_preserva_o_progresso_registrado(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');
        $partida->registrarProgresso('ana', 50);
        $partida->interromperTentativa('ana');

        $tentativa = $partida->tentativaDe(1, 'ana');
        $this->assertSame(SituacaoTentativa::Interrompida, $tentativa?->situacao);
        $this->assertSame(50, $tentativa?->resultado);
        $this->assertSame('bruno', $partida->vezAtual());
    }

    #[Test]
    public function tentativa_interrompida_nao_pode_ser_reiniciada(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');
        $partida->interromperTentativa('ana');

        $this->expectException(RegraViolada::class);
        $partida->iniciarTentativa('ana');
    }

    // Prazo e pulo

    #[Test]
    public function prazo_comeca_quando_a_vez_abre_e_pula_ao_expirar(): void
    {
        $partida = $this->partidaIniciada();

        $this->relogio->avancar(self::PRAZO - 1);
        $partida->processarPrazos();
        $this->assertSame('ana', $partida->vezAtual());

        $this->relogio->avancar(1);
        $partida->processarPrazos();
        $this->assertSame('bruno', $partida->vezAtual());
        $this->assertEventoRegistrado($partida, TipoEvento::VezPulada, Origem::Sistema);
    }

    #[Test]
    public function tentativa_tem_tres_segundos_de_preparacao_e_um_prazo_completo_para_concluir(): void
    {
        $partida = $this->partidaIniciada();
        $this->relogio->avancar(200);
        $partida->iniciarTentativa('ana');
        $tentativa = $partida->tentativaDe(1, 'ana');
        $this->assertEquals($this->relogio->agora()->modify('+3 seconds'), $tentativa->liberaEm);
        $this->assertEquals($this->relogio->agora()->modify('+303 seconds'), $tentativa->prazoExpiraEm);
        $this->relogio->avancar(self::PRAZO + 2);
        $partida->processarPrazos();
        $this->assertSame(SituacaoTentativa::EmAndamento, $tentativa->situacao);
        // Soma a janela de graça para cobrir o atraso do POST do resultado antes de interromper.
        $this->relogio->avancar(1 + 6);
        $partida->processarPrazos();
        $this->assertSame(SituacaoTentativa::Interrompida, $tentativa->situacao);
        $this->assertSame('bruno', $partida->vezAtual());
        // A vez de bruno abre no instante do prazo da ana (6 s antes de agora, por causa da graça).
        $this->assertEquals($this->relogio->agora()->modify('+'.(300 - 6).' seconds'), $partida->prazoExpiraEm());
        $this->assertEventoRegistrado($partida, TipoEvento::TentativaInterrompida, Origem::Sistema);
    }

    #[Test]
    public function prazo_persistido_nao_reinicia_ea_jogada_iniciada_continua_limitada_na_pausa(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');
        $fim = $partida->tentativaDe(1, 'ana')->prazoExpiraEm;
        $partida = unserialize(serialize($partida));
        $partida->religar($this->relogio);
        $this->assertEquals($fim, $partida->tentativaDe(1, 'ana')->prazoExpiraEm);
        $partida->pausar('ana');
        $this->relogio->avancar(self::PRAZO + 3 + 6);
        $partida->processarPrazos();
        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'ana')->situacao);
        $this->assertTrue($partida->pausada());
        $this->assertNull($partida->prazoExpiraEm());
    }

    #[Test]
    public function prazo_da_proxima_vez_conta_a_partir_de_quando_ela_abre(): void
    {
        $partida = $this->partidaIniciada();
        $this->relogio->avancar(200);
        $partida->iniciarTentativa('ana');
        $partida->concluirTentativa('ana', resultado: 10);

        $this->relogio->avancar(self::PRAZO - 1);
        $partida->processarPrazos();

        $this->assertSame('bruno', $partida->vezAtual());
    }

    #[Test]
    public function admin_pula_vez_pendente_como_acao_intencional(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');
        $partida->concluirTentativa('ana', resultado: 10);

        $partida->pularVez('bruno', por: 'ana');

        $this->assertSame('carla', $partida->vezAtual());
        $this->assertEventoRegistrado($partida, TipoEvento::VezPulada, Origem::Admin);
    }

    #[Test]
    public function admin_nao_pula_tentativa_ja_iniciada(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');

        $this->expectException(RegraViolada::class);
        $partida->pularVez('ana', por: 'ana');
    }

    #[Test]
    public function somente_admin_pula_vez(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->pularVez('ana', por: 'bruno');
    }

    // Pausa

    #[Test]
    public function com_a_partida_pausada_nao_se_inicia_tentativa(): void
    {
        $partida = $this->partidaIniciada();
        $partida->pausar(por: 'ana');

        $this->expectException(RegraViolada::class);
        $partida->iniciarTentativa('ana');
    }

    #[Test]
    public function tentativa_em_andamento_termina_mesmo_com_a_partida_pausada(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');
        $partida->pausar(por: 'ana');
        $partida->concluirTentativa('ana', resultado: 10);

        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, 'ana')?->situacao);
    }

    #[Test]
    public function prazo_nao_corre_na_pausa_e_recomeca_do_zero_ao_despausar(): void
    {
        $partida = $this->partidaIniciada();
        $this->relogio->avancar(200);
        $partida->pausar(por: 'ana');

        $this->relogio->avancar(self::PRAZO * 10);
        $partida->processarPrazos();
        $this->assertSame('ana', $partida->vezAtual());

        $partida->despausar(por: 'ana');
        $this->relogio->avancar(self::PRAZO - 1);
        $partida->processarPrazos();
        $this->assertSame('ana', $partida->vezAtual());

        $this->relogio->avancar(1);
        $partida->processarPrazos();
        $this->assertSame('bruno', $partida->vezAtual());
    }

    #[Test]
    public function somente_admin_pausa(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->pausar(por: 'bruno');
    }

    #[Test]
    public function rodada_inteira_sem_tentativa_pausa_a_partida_automaticamente(): void
    {
        $partida = $this->partidaIniciada();

        $this->relogio->avancar(self::PRAZO * 3);
        $partida->processarPrazos();

        $this->assertTrue($partida->pausada());
        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertEventoRegistrado($partida, TipoEvento::PartidaPausada, Origem::Sistema);
    }

    // Apoio

    private function novaPartida(string ...$outrosJogadores): Partida
    {
        $partida = Partida::criar(
            new ConfiguracaoPartida(minimoJogadores: 2, maximoJogadores: 6, prazoVezSegundos: self::PRAZO),
            admin: 'ana',
            relogio: $this->relogio,
        );
        foreach ($outrosJogadores as $jogador) {
            $partida->adicionarParticipante($jogador);
        }

        return $partida;
    }

    private function partidaIniciada(): Partida
    {
        $partida = $this->novaPartida('bruno', 'carla');
        $partida->iniciar(por: 'ana');

        return $partida;
    }

    private function assertEventoRegistrado(Partida $partida, TipoEvento $tipo, Origem $origem): void
    {
        foreach ($partida->eventos() as $evento) {
            if ($evento->tipo === $tipo && $evento->origem === $origem) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("Evento {$tipo->value} com origem {$origem->value} não registrado.");
    }
}
