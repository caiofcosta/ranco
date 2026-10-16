<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Partida;

use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\MotivoEncerramento;
use App\Dominio\Partida\Origem;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Partida\TipoEvento;
use App\Dominio\RegraViolada;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\MontaPartidas;

final class PartidaParalelaTest extends TestCase
{
    use MontaPartidas;

    /** @param list<string> $jogadores */
    private function paralela(array $jogadores = ['ana', 'bruno', 'carla']): Partida
    {
        return $this->partidaIniciada($jogadores, $this->configuracao(modo: ModoExecucao::Paralelo));
    }

    #[Test]
    public function tentativas_paralelas_expiram_individualmente_depois_da_preparacao(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('ana');
        $this->relogio->avancar(20);
        $partida->iniciarTentativa('bruno');
        // Soma 6 s à passagem para cobrir a janela de graça do processarPrazos.
        $this->relogio->avancar(self::PRAZO - 17 + 6);
        $partida->processarPrazos();
        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'ana')->situacao);
        $this->assertSame(SituacaoTentativa::EmAndamento, $partida->tentativaDe(1, 'bruno')->situacao);
        $this->assertSame(['bruno'], $partida->pendentesDaRodada());
        $this->relogio->avancar(20);
        $partida->processarPrazos();
        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'bruno')->situacao);
        $this->assertSame(2, $partida->rodadaAtual());
    }

    #[Test]
    public function rodada_abre_para_todos_sem_vez(): void
    {
        $partida = $this->paralela();

        $this->assertSame(ModoExecucao::Paralelo, $partida->modo());
        $this->assertNull($partida->vezAtual());
        $this->assertSame(['ana', 'bruno', 'carla'], $partida->pendentesDaRodada());
        foreach (['ana', 'bruno', 'carla'] as $jogador) {
            $this->assertTrue($partida->podeJogarAgora($jogador));
        }
        $this->assertFalse($partida->podeJogarAgora('davi'));
    }

    #[Test]
    public function cada_um_joga_em_qualquer_ordem_ea_rodada_fecha_quando_todos_concluem(): void
    {
        $partida = $this->paralela();

        $this->jogar($partida, 'carla', 10);
        $this->jogar($partida, 'ana', 5);
        $this->assertSame(1, $partida->rodadaAtual());
        $this->assertSame(['bruno'], $partida->pendentesDaRodada());
        $this->assertFalse($partida->podeJogarAgora('carla'));

        $this->jogar($partida, 'bruno', 8);

        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame(1, $partida->posicaoNaRodada(1, 'carla'));
        $this->assertSame(2, $partida->posicaoNaRodada(1, 'bruno'));
        $this->assertSame(3, $partida->posicaoNaRodada(1, 'ana'));
        $this->assertSame(['ana', 'bruno', 'carla'], $partida->pendentesDaRodada());
    }

    #[Test]
    public function tentativas_correm_ao_mesmo_tempo(): void
    {
        $partida = $this->paralela();

        $partida->iniciarTentativa('ana');
        $partida->iniciarTentativa('bruno');
        $partida->concluirTentativa('bruno', resultado: 7);
        $partida->concluirTentativa('ana', resultado: 9);

        $this->assertSame(['carla'], $partida->pendentesDaRodada());
        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, 'ana')?->situacao);
        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, 'bruno')?->situacao);
    }

    #[Test]
    public function nao_inicia_duas_vezes_nem_depois_de_concluir(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('ana');

        try {
            $partida->iniciarTentativa('ana');
            $this->fail('Iniciou duas vezes a mesma jogada.');
        } catch (RegraViolada) {
            $this->addToAssertionCount(1);
        }

        $partida->concluirTentativa('ana', resultado: 5);
        $this->expectException(RegraViolada::class);
        $partida->iniciarTentativa('ana');
    }

    #[Test]
    public function prazo_vencido_tira_quem_nao_comecou_e_espera_quem_ja_comecou(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('ana');
        $this->jogar($partida, 'carla', 10);

        $this->relogio->avancar(self::PRAZO);
        $partida->processarPrazos();

        $this->assertSame(1, $partida->rodadaAtual());
        $this->assertSame(['ana'], $partida->pendentesDaRodada());
        $this->assertFalse($partida->podeJogarAgora('bruno'));
        $this->assertNull($partida->prazoExpiraEm());
        $this->assertEventoRegistrado($partida, TipoEvento::VezPulada, Origem::Sistema);

        $partida->concluirTentativa('ana', resultado: 4);

        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame(0, $partida->pontosNaRodada(1, 'bruno'));
        $this->assertSame(3, $partida->pontosNaRodada(1, 'carla'));
    }

    #[Test]
    public function prazo_vencido_sem_ninguem_jogando_fecha_a_rodada_e_abre_a_proxima(): void
    {
        $partida = $this->paralela();
        $this->jogar($partida, 'ana', 5);

        $this->relogio->avancar(self::PRAZO);
        $partida->processarPrazos();

        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame(['ana', 'bruno', 'carla'], $partida->pendentesDaRodada());
        $this->assertEquals($this->relogio->agora()->modify('+'.self::PRAZO.' seconds'), $partida->prazoExpiraEm());
    }

    #[Test]
    public function rodada_sem_nenhuma_jogada_pausa_a_partida(): void
    {
        $partida = $this->paralela();

        $this->relogio->avancar(self::PRAZO);
        $partida->processarPrazos();

        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertTrue($partida->pausada());
        $this->assertNull($partida->prazoExpiraEm());
    }

    #[Test]
    public function nao_ha_vez_para_pular(): void
    {
        $partida = $this->paralela();

        $this->expectException(RegraViolada::class);
        $partida->pularVez('bruno', por: 'ana');
    }

    #[Test]
    public function pausa_impede_novas_jogadas_mas_a_iniciada_termina_eo_despausar_recomeca_o_prazo(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('ana');
        $partida->pausar(por: 'ana');

        try {
            $partida->iniciarTentativa('bruno');
            $this->fail('Iniciou jogada com a partida pausada.');
        } catch (RegraViolada) {
            $this->addToAssertionCount(1);
        }

        $partida->concluirTentativa('ana', resultado: 6);
        $this->relogio->avancar(1000);
        $partida->despausar(por: 'ana');

        $this->assertEquals($this->relogio->agora()->modify('+'.self::PRAZO.' seconds'), $partida->prazoExpiraEm());
        $this->assertSame(['bruno', 'carla'], $partida->pendentesDaRodada());
    }

    #[Test]
    public function despausar_sem_ninguem_para_comecar_nao_liga_o_prazo(): void
    {
        $partida = $this->paralela(['ana', 'bruno']);
        $partida->iniciarTentativa('ana');
        $partida->iniciarTentativa('bruno');
        $partida->pausar(por: 'ana');
        $partida->despausar(por: 'ana');

        $this->assertNull($partida->prazoExpiraEm());
    }

    #[Test]
    public function encerrar_espera_todas_as_tentativas_iniciadas(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('ana');
        $partida->iniciarTentativa('bruno');

        $partida->encerrar(por: 'ana');
        $this->assertTrue($partida->encerramentoSolicitado());

        $partida->concluirTentativa('ana', resultado: 5);
        $this->assertSame(SituacaoPartida::EmAndamento, $partida->situacao());

        $partida->concluirTentativa('bruno', resultado: 6);
        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::EncerradaPeloAdmin, $partida->motivoEncerramento());
    }

    #[Test]
    public function encerrar_a_mesa_interrompe_todas_as_tentativas(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('ana');
        $partida->iniciarTentativa('bruno');

        $partida->encerrarPorEncerramentoDaMesa();

        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'ana')?->situacao);
        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'bruno')?->situacao);
        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
    }

    #[Test]
    public function encerrar_ou_abandonar_a_mesa_com_identificadores_numericos_interrompe_todas_nos_dois_modos(): void
    {
        foreach ([ModoExecucao::Sequencial, ModoExecucao::Paralelo] as $modo) {
            foreach (['encerrarPorEncerramentoDaMesa', 'encerrarPorAbandonoDoAdmin'] as $acao) {
                // Contas reais usam (string) id: a chave '29' de um array vira o inteiro 29.
                $partida = $this->partidaIniciada(['29', '30'], $this->configuracao(modo: $modo));
                $jogando = $modo === ModoExecucao::Paralelo ? ['29', '30'] : ['29'];
                foreach ($jogando as $jogador) {
                    $partida->iniciarTentativa($jogador);
                }

                $partida->{$acao}();

                $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao(), "{$modo->value}/{$acao}");
                $this->assertNull($partida->vencedor());
                $interrompidos = [];
                foreach ($partida->eventos() as $evento) {
                    if ($evento->tipo === TipoEvento::TentativaInterrompida) {
                        $interrompidos[] = $evento->participante;
                    }
                }
                $this->assertSame($jogando, $interrompidos, "{$modo->value}/{$acao}");
                foreach ($jogando as $jogador) {
                    $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, $jogador)?->situacao);
                }
            }
        }
    }

    #[Test]
    public function saida_do_ultimo_pendente_fecha_a_rodada(): void
    {
        $partida = $this->paralela(['ana', 'bruno', 'carla', 'davi']);
        $this->jogar($partida, 'ana', 5);
        $this->jogar($partida, 'bruno', 7);
        $this->jogar($partida, 'carla', 9);

        $partida->abandonar('davi');

        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame(['ana', 'bruno', 'carla'], $partida->pendentesDaRodada());
    }

    #[Test]
    public function quem_abandona_no_meio_da_jogada_tem_a_tentativa_interrompida(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('bruno');

        $partida->abandonar('bruno');

        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'bruno')?->situacao);
        $this->assertSame(['ana', 'carla'], $partida->pendentesDaRodada());
    }

    #[Test]
    public function nao_remove_quem_esta_jogando(): void
    {
        $partida = $this->paralela();
        $partida->iniciarTentativa('bruno');

        $this->expectException(RegraViolada::class);
        $partida->removerParticipante('bruno', por: 'ana');
    }

    #[Test]
    public function limite_de_rodadas_encerra_com_vencedor(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno'], $this->configuracao(modo: ModoExecucao::Paralelo, limiteRodadas: 1));

        $this->jogar($partida, 'bruno', 9);
        $this->jogar($partida, 'ana', 4);

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame('bruno', $partida->vencedor());
    }

    #[Test]
    public function configuracao_gravada_antes_do_modo_continua_sequencial(): void
    {
        $atual = serialize(new ConfiguracaoPartida(minimoJogadores: 2, maximoJogadores: 6, prazoVezSegundos: 300));
        $semModo = preg_replace('/s:4:"modo";E:\d+:"[^"]+";/', '', $atual);
        $semModo = str_replace(':10:{', ':9:{', (string) $semModo);
        $this->assertStringNotContainsString('modo', $semModo);

        $antiga = unserialize($semModo);

        $this->assertInstanceOf(ConfiguracaoPartida::class, $antiga);
        $this->assertSame(ModoExecucao::Sequencial, $antiga->modo);
        $this->assertSame(300, $antiga->prazoVezSegundos);
    }
}
