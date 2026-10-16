<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Partida\TipoEvento;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Relogio;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\LacraOTempo;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class JogarVezTest extends TestCase
{
    use LacraOTempo;
    use RefreshDatabase;

    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-02 12:00:00'));
        $this->app->instance(Relogio::class, $this->relogio);
    }

    #[Test]
    public function exige_login_e_email_verificado_nas_tres_rotas(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $this->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('login'));
        foreach (['iniciar', 'resultado'] as $acao) {
            $this->postJson(route('mesas.jogar.'.$acao, $mesa->id), $this->dados())->assertUnauthorized();
        }
        $ana->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('verification.notice'));
        foreach (['iniciar', 'resultado'] as $acao) {
            $this->postJson(route('mesas.jogar.'.$acao, $mesa->id), $this->dados())->assertForbidden();
        }
    }

    #[Test]
    public function somente_membros_ativos_acessam_ou_enviam_pedidos(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $estranho = User::factory()->create();
        $this->conferirAcessoNegado($mesa->id, $estranho);
        app(Plataforma::class)->abandonarMesa($mesa->id, $bruno->identificadorNoDominio());
        $this->conferirAcessoNegado($mesa->id, $bruno);
        $this->actingAs($ana)->get(route('mesas.jogar', 'inexistente'))->assertNotFound();
    }

    #[Test]
    public function sem_partida_em_configuracao_pausada_e_fora_de_vez_voltam_para_mesa(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa(false);
        $plataforma = app(Plataforma::class);
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $plataforma->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, null), $ana->identificadorNoDominio()));
        $this->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $plataforma->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()->iniciar($ana->identificadorNoDominio()));
        $this->actingAs($bruno)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $plataforma->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()->pausar($ana->identificadorNoDominio()));
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $plataforma->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()->encerrar($ana->identificadorNoDominio()));
        $this->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
    }

    #[Test]
    public function mostra_previa_travada_e_inicio_explicito_nao_reinicia_no_duplo_envio(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $pessoa = $ana->identificadorNoDominio();
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))
            ->assertOk()->assertSee('Iniciar sua vez')->assertSee('data-antes-de-iniciar="1"', false);
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $pessoa));

        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect(route('mesas.jogar', $mesa->id));
        $primeiroPrazo = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $pessoa)->prazoExpiraEm;
        $this->relogio->avancar(2);
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect(route('mesas.jogar', $mesa->id));
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(SituacaoTentativa::EmAndamento, $partida->tentativaDe(1, $pessoa)->situacao);
        $eventos = array_filter($partida->eventos(), fn ($evento) => $evento->tipo === TipoEvento::TentativaIniciada);
        $this->assertCount(1, $eventos);
        $this->assertEquals($primeiroPrazo, $partida->tentativaDe(1, $pessoa)->prazoExpiraEm);
        $this->get(route('mesas.jogar', $mesa->id))->assertOk()->assertSee('data-labirinto-partida', false)->assertDontSee('Iniciar sua vez');
    }

    #[Test]
    public function resultado_tardio_e_aceito_se_a_tentativa_ainda_nao_foi_processada(): void
    {
        // O jogo pode terminar no cliente exatamente no fim do prazo (Moedinha), ou o POST
        // pode chegar com atraso de rede. Enquanto nada processou o prazo, a tentativa é
        // concluída normalmente.
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->relogio->avancar($partida->configuracao()->prazoVezSegundos + 3);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 60000]))->assertOk();
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $tentativa = $partida->tentativaDe(1, $ana->identificadorNoDominio());
        $this->assertSame(SituacaoTentativa::Concluida, $tentativa->situacao);
        $this->assertSame(60000, $tentativa->resultado);
        $this->assertSame($bruno->identificadorNoDominio(), $partida->vezAtual());
    }

    #[Test]
    public function aceita_o_tempo_da_tela_mesmo_menor_que_o_decorrido_no_servidor(): void
    {
        // A latência do celular faz a tela medir menos que o servidor; isso não pode recusar a vez.
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        $this->relogio->avancar(30);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 12000]))->assertOk();
        $tentativa = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $ana->identificadorNoDominio());
        $this->assertSame(SituacaoTentativa::Concluida, $tentativa->situacao);
        $this->assertSame(12000, $tentativa->resultado);
    }

    #[Test]
    public function recusa_tempo_sem_lacre_ou_com_lacre_de_outro_tempo(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), [...$dados, 'tempo' => 9000])
            ->assertUnprocessable()->assertJsonPath('pode_reenviar', false);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), [...$this->lacrado([...$dados, 'tempo' => 12000]), 'tempo' => 9000])
            ->assertUnprocessable();
        $tentativa = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $ana->identificadorNoDominio());
        $this->assertSame(SituacaoTentativa::EmAndamento, $tentativa->situacao);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 12000]))->assertOk();
    }

    #[Test]
    public function prazo_processado_por_outro_acesso_recusa_resultado_tardio_e_passa_a_vez(): void
    {
        // Se alguém (outro jogador, outra tela, agendador) carregou a mesa muito depois do prazo
        // vencer — fora da janela de graça — a tentativa já está interrompida: o POST devolve 409.
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->relogio->avancar($partida->configuracao()->prazoVezSegundos + 30);
        app(Plataforma::class)->mesa($mesa->id);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 60000]))->assertConflict();
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, $ana->identificadorNoDominio())->situacao);
        $this->assertSame($bruno->identificadorNoDominio(), $partida->vezAtual());
    }

    #[Test]
    public function conclui_tentativas_eo_proprio_dominio_calcula_o_placar(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        foreach ([[$ana, 12000], [$bruno, 15000]] as [$pessoa, $tempo]) {
            $dados = $this->dadosDaVez($mesa, $pessoa);
            $this->actingAs($pessoa)->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect();
            $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => $tempo]))
                ->assertOk()->assertExactJson(['destino' => route('mesas.jogo', ['id' => $mesa->id, 'partida' => $dados['partida']])]);
        }

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(12000, $partida->tentativaDe(1, $ana->identificadorNoDominio())->resultado);
        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, $ana->identificadorNoDominio())->situacao);
        $this->assertSame(1, $partida->posicaoNaRodada(1, $ana->identificadorNoDominio()));
        $this->assertSame(3, $partida->totalDe($ana->identificadorNoDominio()));
        $this->assertSame(2, $partida->totalDe($bruno->identificadorNoDominio()));
        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertDatabaseCount('melhores_resultados', 0);
    }

    #[Test]
    public function rejeita_tempo_invalido_sem_consumir_a_conclusao(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        foreach ([null, 0, -1, 3999, 5000.5, 'abc', [], true, 86400001] as $tempo) {
            $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => $tempo]))
                ->assertUnprocessable()->assertJsonValidationErrors('tempo');
        }
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(SituacaoTentativa::EmAndamento, $partida->tentativaDe(1, $ana->identificadorNoDominio())->situacao);
    }

    #[Test]
    public function exige_inicio_antes_do_resultado_e_nao_permite_jogar_pelo_outro(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertConflict()->assertJsonPath('pode_reenviar', false);
        $this->actingAs($bruno)->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect(route('mesas.mostrar', $mesa->id))->assertSessionHasErrors('vez');
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'participante' => $ana->id]))->assertConflict();
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $ana->identificadorNoDominio()));
    }

    #[Test]
    public function resultado_da_vez_que_mudou_retorna_mensagem_sem_erro_interno(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $pessoa = $ana->identificadorNoDominio();
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        app(Plataforma::class)->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()->interromperTentativa($pessoa));
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertConflict()
            ->assertJsonPath('mensagem', 'Sua vez não está mais disponível. Volte para a mesa para acompanhar.');
    }

    #[Test]
    /** Regra confirmada: na pausa, nenhuma tentativa nova começa, mas as já iniciadas terminam. */
    public function pausa_entre_inicio_e_envio_aceita_o_resultado_da_tentativa_iniciada(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        app(Plataforma::class)->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()->pausar($ana->identificadorNoDominio()));

        $this->get(route('mesas.jogar', $mesa->id))->assertOk()->assertSee('data-labirinto-partida', false);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, $ana->identificadorNoDominio())->situacao);
        $this->assertTrue($partida->pausada());
        $this->actingAs($bruno)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
    }

    #[Test]
    public function reenvio_apos_resposta_perdida_nao_duplica_nem_troca_resultado(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 9000]))->assertConflict();
        $eventos = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->eventos();
        $this->assertCount(1, array_filter($eventos, fn ($evento) => $evento->tipo === TipoEvento::TentativaConcluida));
    }

    #[Test]
    public function contexto_antigo_nao_inicia_nem_conclui_a_nova_rodada(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        foreach ([[$ana, 10000], [$bruno, 12000]] as [$pessoa, $tempo]) {
            $dados = $this->dadosDaVez($mesa, $pessoa);
            $jogadas[$pessoa->id] = $dados;
            $this->actingAs($pessoa)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
            $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => $tempo]))->assertOk();
        }
        $dados = $jogadas[$ana->id];
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertSessionHasErrors('vez');
        // O recibo antigo pode ser confirmado, mas não inicia nem conclui a nova vez.
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(2, $ana->identificadorNoDominio()));
    }

    #[Test]
    public function empate_nao_abre_desempate_e_segue_para_a_proxima_rodada(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        foreach ([$ana, $bruno] as $pessoa) {
            $dados = $this->dadosDaVez($mesa, $pessoa);
            $this->actingAs($pessoa)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
            $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();
        }

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(0, $partida->desempateAtual());
        $this->assertSame(2, $partida->rodadaAtual());
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertOk()->assertDontSee('Desempate');
    }

    #[Test]
    public function prazo_vencido_entre_tela_e_post_e_processado_pelo_dominio(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertOk();
        $this->relogio->avancar(121);
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect(route('mesas.mostrar', $mesa->id))->assertSessionHasErrors('vez');
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $ana->identificadorNoDominio()));
    }

    #[Test]
    public function contexto_de_partida_anterior_nao_afeta_nova_partida(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        app(Plataforma::class)->naMesa($mesa->id, function (Mesa $mesa) use ($ana): void {
            $mesa->partidaAtiva()->encerrar($ana->identificadorNoDominio());
            $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, null), $ana->identificadorNoDominio())->iniciar($ana->identificadorNoDominio());
        });
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertSessionHasErrors('vez');
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertConflict();
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $ana->identificadorNoDominio()));
    }

    #[Test]
    public function admin_joga_pelo_local_eo_placar_pertence_ao_local(): void
    {
        [$mesa, $ana, $bruno, $local] = $this->prepararMesaComLocais();
        foreach ([[$ana, 15000], [$bruno, 12000], [$ana, 9000]] as [$usuario, $tempo]) {
            $dados = $this->dadosDaVez($mesa, $usuario);
            $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect(route('mesas.jogar', $mesa->id));
            $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => $tempo]))->assertOk();
        }

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(9000, $partida->tentativaDe(1, $local)->resultado);
        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, $local)->situacao);
        $this->assertSame(15000, $partida->tentativaDe(1, $ana->identificadorNoDominio())->resultado);
        $this->assertSame(3, $partida->totalDe($local));
        $this->assertSame(1, $partida->totalDe($ana->identificadorNoDominio()));
        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertDatabaseCount('melhores_resultados', 0);
    }

    #[Test]
    public function somente_admin_joga_o_local_e_as_telas_mostram_seu_nome(): void
    {
        [$mesa, $ana, $bruno, $local] = $this->prepararMesaComLocais();
        $this->avancarAteLocal($mesa, $ana, $bruno);
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->get(route('mesas.jogar', $mesa->id))->assertOk()
            ->assertSee('Vez de Carla')->assertSee('Jogar pela vez de Carla')->assertDontSee('Iniciar sua vez');
        $this->actingAs($bruno)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertSessionHasErrors('vez');
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertConflict();
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $local));

        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect(route('mesas.jogar', $mesa->id));
        $this->get(route('mesas.jogar', $mesa->id))->assertOk()
            ->assertSee('Carla está jogando')->assertSee('data-labirinto-partida', false);
        $this->actingAs($bruno)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertConflict();
        $this->assertSame(SituacaoTentativa::EmAndamento, app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $local)->situacao);
    }

    #[Test]
    public function admin_nao_pode_jogar_pela_outra_conta(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        app(Plataforma::class)->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()->pularVez($ana->identificadorNoDominio(), $ana->identificadorNoDominio()));
        $dados = $this->dadosDaVez($mesa, $bruno);
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertSessionHasErrors('vez');
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'participante' => $ana->id]))->assertConflict();
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $bruno->identificadorNoDominio()));
    }

    #[Test]
    public function reenvios_de_um_local_nao_iniciam_nem_concluem_o_proximo_na_mesma_rodada(): void
    {
        [$mesa, $ana, $bruno, $local, $outroLocal] = $this->prepararMesaComLocais(true);
        $this->avancarAteLocal($mesa, $ana, $bruno);
        $primeiraJogada = $this->dadosDaVez($mesa, $ana);
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $primeiraJogada)->assertRedirect();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($primeiraJogada))->assertOk();
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $primeiraJogada)->assertSessionHasErrors('vez');
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $outroLocal));

        $segundaJogada = $this->dadosDaVez($mesa, $ana);
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $segundaJogada)->assertRedirect();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($primeiraJogada))->assertOk();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$primeiraJogada, 'tempo' => 9000]))->assertConflict();
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(10000, $partida->tentativaDe(1, $local)->resultado);
        $this->assertSame(SituacaoTentativa::EmAndamento, $partida->tentativaDe(1, $outroLocal)->situacao);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$segundaJogada, 'tempo' => 12000]))->assertOk();
        $this->assertSame(12000, app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $outroLocal)->resultado);
    }

    #[Test]
    public function local_interrompido_nao_conclui_a_proxima_vez_e_comprovante_alterado_e_negado(): void
    {
        [$mesa, $ana, $bruno, $local, $outroLocal] = $this->prepararMesaComLocais(true);
        $this->avancarAteLocal($mesa, $ana, $bruno);
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->post(route('mesas.jogar.iniciar', $mesa->id), [...$dados, 'jogada' => 'alterada'])->assertSessionHasErrors('vez');
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'jogada' => 'alterada']))->assertConflict();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'rodada' => 2]))->assertConflict();
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect();
        app(Plataforma::class)->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()->interromperTentativa($local));
        $proximaJogada = $this->dadosDaVez($mesa, $ana);
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $proximaJogada)->assertRedirect();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertConflict();
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(SituacaoTentativa::EmAndamento, $partida->tentativaDe(1, $outroLocal)->situacao);
    }

    #[Test]
    public function fluxo_completo_com_duas_e_tres_contas_pontua_encerra_e_preserva_solo(): void
    {
        foreach ([2, 3] as $quantidade) {
            $pessoas = User::factory()->count($quantidade)->create()->all();
            $admin = $pessoas[0];
            $plataforma = app(Plataforma::class);
            $mesa = $plataforma->criarMesa($admin->identificadorNoDominio(), 'Fluxo '.$quantidade);
            $plataforma->configurarLugares($mesa->id, $quantidade, $admin->identificadorNoDominio());
            foreach (array_slice($pessoas, 1) as $pessoa) {
                $plataforma->naMesa($mesa->id, fn (Mesa $m) => $m->adicionarMembro($pessoa->identificadorNoDominio()));
            }
            $this->actingAs($admin)->post(route('mesas.partidas.criar', $mesa->id), [
                'personalizar_pontos' => 1, 'pontos_por_posicao' => [10, 5, 2, 0],
                'meta_pontos' => '', 'limite_rodadas' => 2,
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->post(route('mesas.partida.iniciar', $mesa->id))->assertSessionHasNoErrors()->assertRedirect();
            for ($rodada = 1; $rodada <= 2; $rodada++) {
                foreach ($pessoas as $indice => $pessoa) {
                    $dados = $this->dadosDaVez($mesa, $pessoa);
                    $this->assertSame($rodada, $dados['rodada']);
                    $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect();
                    $this->get(route('mesas.jogar', $mesa->id))->assertOk()->assertSee('data-labirinto-partida', false);
                    $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 10000 + $indice * 2000]))
                        ->assertOk()->assertJsonPath('destino', route('mesas.jogo', ['id' => $mesa->id, 'partida' => $dados['partida']]));
                }
            }
            $final = $plataforma->mesa($mesa->id)->partidas()[0];
            $this->assertSame(SituacaoPartida::Encerrada, $final->situacao());
            foreach ($pessoas as $indice => $pessoa) {
                $this->assertSame([20, 10, 4][$indice], $final->totalDe($pessoa->identificadorNoDominio()));
            }
            $this->assertSame($admin->identificadorNoDominio(), $final->vencedor());
            $this->actingAs($admin)->get(route('mesas.mostrar', $mesa->id))->assertOk();
            $this->assertDatabaseCount('melhores_resultados', 0);
            $this->assertDatabaseCount('tentativas_solo_labirinto', 0);
            $this->post(route('mesas.partidas.criar', $mesa->id), [])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertCount(2, $plataforma->mesa($mesa->id)->partidas());
        }
    }

    private function avancarAteLocal(Mesa $mesa, User $ana, User $bruno): void
    {
        app(Plataforma::class)->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $bruno): void {
            foreach ([$ana, $bruno] as $usuario) {
                $mesa->partidaAtiva()->pularVez($usuario->identificadorNoDominio(), $ana->identificadorNoDominio());
            }
        });
    }

    /** @return array{Mesa, User, User, string, ?string} */
    private function prepararMesaComLocais(bool $doisLocais = false): array
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa(false);
        $local = null;
        $outroLocal = null;
        app(Plataforma::class)->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $doisLocais, &$local, &$outroLocal): void {
            $local = $mesa->cadastrarParticipanteLocal('Carla', 'carla@example.test', $ana->identificadorNoDominio());
            if ($doisLocais) {
                $outroLocal = $mesa->cadastrarParticipanteLocal('Davi', 'davi@example.test', $ana->identificadorNoDominio());
            }
            $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, null), $ana->identificadorNoDominio())->iniciar($ana->identificadorNoDominio());
        });

        return [$mesa, $ana, $bruno, $local, $outroLocal];
    }

    private function dadosDaVez(Mesa $mesa, User $usuario): array
    {
        $resposta = $this->actingAs($usuario)->get(route('mesas.jogar', $mesa->id))->assertOk();

        return [
            'jogada' => $resposta->viewData('jogada'),
            'partida' => $resposta->viewData('partida'),
            'rodada' => $resposta->viewData('rodada'),
            'desempate' => $resposta->viewData('desempate'),
            'tempo' => 10000,
        ];
    }

    private function dados(int $tempo = 10000): array
    {
        return ['partida' => 1, 'rodada' => 1, 'desempate' => 0, 'tempo' => $tempo];
    }

    /** @return array{Mesa, User, User} */
    private function prepararMesa(bool $iniciar = true): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Mesa de teste');
        $plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $bruno, $iniciar): void {
            $mesa->adicionarMembro($bruno->identificadorNoDominio());
            if ($iniciar) {
                $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, null), $ana->identificadorNoDominio())->iniciar($ana->identificadorNoDominio());
            }
        });

        return [$mesa, $ana, $bruno];
    }

    private function conferirAcessoNegado(string $mesa, User $pessoa): void
    {
        $this->actingAs($pessoa)->get(route('mesas.jogar', $mesa))->assertNotFound();
        foreach (['iniciar', 'resultado'] as $acao) {
            $this->postJson(route('mesas.jogar.'.$acao, $mesa), $this->dados())->assertNotFound();
        }
    }
}
