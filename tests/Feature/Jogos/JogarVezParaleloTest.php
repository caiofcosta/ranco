<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Relogio;
use App\Livewire\Mesa\MostrarMesa;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\LacraOTempo;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class JogarVezParaleloTest extends TestCase
{
    use LacraOTempo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Relogio::class, new RelogioFalso(new DateTimeImmutable('2026-10-04 12:00:00')));
    }

    #[Test]
    public function cada_pessoa_joga_a_sua_rodada_ao_mesmo_tempo(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $dadosDaAna = $this->dadosDaVez($mesa, $ana);
        $dadosDoBruno = $this->dadosDaVez($mesa, $bruno);

        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dadosDaAna)->assertRedirect(route('mesas.jogar', $mesa->id));
        $this->actingAs($bruno)->post(route('mesas.jogar.iniciar', $mesa->id), $dadosDoBruno)->assertRedirect(route('mesas.jogar', $mesa->id));
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(SituacaoTentativa::EmAndamento, $partida->tentativaDe(1, $ana->identificadorNoDominio())->situacao);
        $this->assertSame(SituacaoTentativa::EmAndamento, $partida->tentativaDe(1, $bruno->identificadorNoDominio())->situacao);

        $this->actingAs($bruno)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dadosDoBruno, 'tempo' => 9000]))->assertOk();
        $this->actingAs($ana)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dadosDaAna, 'tempo' => 12000]))->assertOk();

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame(1, $partida->posicaoNaRodada(1, $bruno->identificadorNoDominio()));
        $this->assertSame(2, $partida->posicaoNaRodada(1, $ana->identificadorNoDominio()));
    }

    #[Test]
    public function na_pausa_quem_ja_comecou_termina_e_quem_nao_comecou_nao_comeca(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $dadosDaAna = $this->dadosDaVez($mesa, $ana);
        $dadosDoBruno = $this->dadosDaVez($mesa, $bruno);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dadosDaAna);
        app(Plataforma::class)->pausarPartida($mesa->id, $ana->identificadorNoDominio());

        $this->actingAs($bruno)->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dadosDoBruno)->assertSessionHasErrors('vez');
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertOk()->assertSee('data-labirinto-partida', false);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dadosDaAna, 'tempo' => 9000]))->assertOk();

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, $ana->identificadorNoDominio())->situacao);
        $this->assertNull($partida->tentativaDe(1, $bruno->identificadorNoDominio()));
        $this->assertSame([$bruno->identificadorNoDominio()], $partida->pendentesDaRodada());
    }

    #[Test]
    public function encerrar_ou_abandonar_a_mesa_com_alguem_jogando_grava_nos_dois_modos(): void
    {
        foreach ([ModoExecucao::Sequencial, ModoExecucao::Paralelo] as $modo) {
            foreach (['encerrarMesa', 'abandonarMesa'] as $acao) {
                [$mesa, $ana, $bruno] = $this->prepararMesa(modo: $modo);
                $jogando = $modo === ModoExecucao::Paralelo ? [$ana, $bruno] : [$ana];
                foreach ($jogando as $pessoa) {
                    $this->actingAs($pessoa)->post(route('mesas.jogar.iniciar', $mesa->id), $this->dadosDaVez($mesa, $pessoa));
                }

                app(Plataforma::class)->{$acao}($mesa->id, $ana->identificadorNoDominio());

                $gravada = app(Plataforma::class)->mesa($mesa->id);
                $partida = $gravada->partidas()[0];
                $this->assertSame(SituacaoMesa::Encerrada, $gravada->situacao(), "{$modo->value}/{$acao}");
                $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
                $this->assertNull($partida->vencedor());
                foreach ($jogando as $pessoa) {
                    $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, $pessoa->identificadorNoDominio())->situacao);
                }
            }
        }
    }

    #[Test]
    public function quem_ja_jogou_a_rodada_volta_para_a_mesa(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();

        $this->get(route('mesas.jogar', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 11000]))->assertConflict();
    }

    #[Test]
    public function comprovante_de_uma_pessoa_nao_serve_para_outra(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $dadosDaAna = $this->dadosDaVez($mesa, $ana);

        $this->actingAs($bruno)->post(route('mesas.jogar.iniciar', $mesa->id), $dadosDaAna)
            ->assertRedirect(route('mesas.mostrar', $mesa->id))->assertSessionHasErrors('vez');
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertNull($partida->tentativaDe(1, $ana->identificadorNoDominio()));
        $this->assertNull($partida->tentativaDe(1, $bruno->identificadorNoDominio()));
    }

    #[Test]
    public function tela_mostra_quem_falta_eo_botao_so_para_quem_nao_jogou(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $this->actingAs($bruno)->get(route('mesas.jogo', $mesa->id))
            ->assertOk()->assertSee('Falta jogar: Ana, Bruno')->assertSee('data-test="jogar-minha-vez"', false)->assertDontSee('Vez de');

        $dados = $this->dadosDaVez($mesa, $ana);
        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesa->id), $dados);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado($dados))->assertOk();

        $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id))
            ->assertOk()->assertSee('Falta jogar: Bruno')->assertDontSee('data-test="jogar-minha-vez"', false);
        $this->actingAs($bruno)->get(route('mesas.jogo', $mesa->id))
            ->assertOk()->assertSee('data-test="jogar-minha-vez"', false)->assertDontSee('data-test="pular-vez"', false);
    }

    #[Test]
    public function admin_escolhe_o_modo_ao_criar_a_partida(): void
    {
        [$mesa, $ana] = $this->prepararMesa(iniciar: false);
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('Um por vez')->assertSee('Todos na mesma rodada')
            ->set('modo', 'paralelo')
            ->call('criarPartida')
            ->assertHasNoErrors();

        $this->assertSame(ModoExecucao::Paralelo, app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->modo());
    }

    #[Test]
    public function modo_invalido_nao_cria_a_partida(): void
    {
        [$mesa, $ana] = $this->prepararMesa(iniciar: false);
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->set('modo', 'livre')
            ->call('criarPartida')
            ->assertHasErrors(['modo']);
        $this->post(route('mesas.partidas.criar', $mesa->id), ['modo' => 'livre'])->assertSessionHasErrors('modo');

        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva());
    }

    #[Test]
    public function formulario_sem_java_script_cria_no_modo_escolhido_ou_no_padrao(): void
    {
        [$mesa, $ana] = $this->prepararMesa(iniciar: false);
        $plataforma = app(Plataforma::class);

        $this->actingAs($ana)->post(route('mesas.partidas.criar', $mesa->id), ['modo' => 'paralelo'])
            ->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->assertSame(ModoExecucao::Paralelo, $plataforma->mesa($mesa->id)->partidaAtiva()->modo());

        [$outraMesa, $outraAdmin] = $this->prepararMesa(iniciar: false);
        $this->actingAs($outraAdmin)->post(route('mesas.partidas.criar', $outraMesa->id))
            ->assertRedirect(route('mesas.mostrar', $outraMesa->id));
        $this->assertSame(ModoExecucao::Sequencial, $plataforma->mesa($outraMesa->id)->partidaAtiva()->modo());
    }

    #[Test]
    public function participante_local_nao_aparece_para_admitir_no_paralelo(): void
    {
        [$mesa, $ana] = $this->prepararMesa(iniciar: false);
        $plataforma = app(Plataforma::class);
        $plataforma->cadastrarParticipanteLocal($mesa->id, 'Carla Local', 'carla@example.test', $ana->identificadorNoDominio());
        $plataforma->criarPartida(
            $mesa->id,
            CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 120, null, null, ModoExecucao::Paralelo),
            $ana->identificadorNoDominio(),
        );
        $this->actingAs($ana);

        $partida = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])->viewData('partida');

        $this->assertSame([], $partida->admitiveis);
        $this->assertSame(['Ana', 'Bruno'], $partida->participantes);
    }

    /** @return array{Mesa, User, User} */
    #[Test]
    public function com_duas_abertas_so_uma_jogada_comeca_por_vez(): void
    {
        [$mesaA, $ana] = $this->prepararMesa();
        $carla = User::factory()->create(['name' => 'Carla']);
        $plataforma = app(Plataforma::class);
        $mesaB = $plataforma->criarMesa($carla->identificadorNoDominio(), 'Outra mesa');
        $plataforma->naMesa($mesaB->id, static function (Mesa $mesa) use ($ana, $carla): void {
            $mesa->adicionarMembro($ana->identificadorNoDominio());
            $mesa->novaPartida(
                CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 120, null, null, ModoExecucao::Paralelo),
                $carla->identificadorNoDominio(),
            )->iniciar($carla->identificadorNoDominio());
        });
        $dadosA = $this->dadosDaVez($mesaA, $ana);
        $dadosB = $this->dadosDaVez($mesaB, $ana);

        $this->actingAs($ana)->post(route('mesas.jogar.iniciar', $mesaA->id), $dadosA)->assertRedirect(route('mesas.jogar', $mesaA->id));
        $this->post(route('mesas.jogar.iniciar', $mesaB->id), $dadosB)
            ->assertRedirect(route('mesas.mostrar', $mesaB->id))
            ->assertSessionHasErrors(['vez' => 'Você já está jogando em outra mesa. Termine aquela jogada primeiro.']);

        $this->postJson(route('mesas.jogar.resultado', $mesaA->id), $this->lacrado([...$dadosA, 'tempo' => 9000]))->assertOk();
        $this->post(route('mesas.jogar.iniciar', $mesaB->id), $dadosB)->assertRedirect(route('mesas.jogar', $mesaB->id));
    }

    private function prepararMesa(bool $iniciar = true, ModoExecucao $modo = ModoExecucao::Paralelo): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Mesa em paralelo');
        $plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $bruno, $iniciar, $modo): void {
            $mesa->adicionarMembro($bruno->identificadorNoDominio());
            if ($iniciar) {
                $mesa->novaPartida(
                    CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 120, null, null, $modo),
                    $ana->identificadorNoDominio(),
                )->iniciar($ana->identificadorNoDominio());
            }
        });

        return [$mesa, $ana, $bruno];
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
}
