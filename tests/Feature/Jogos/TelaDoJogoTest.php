<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Livewire\Mesa\MostrarMesa;
use App\Livewire\Mesa\PainelDoJogo;
use App\Mesa\TelaDoJogo;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\LacraOTempo;
use Tests\TestCase;

final class TelaDoJogoTest extends TestCase
{
    use LacraOTempo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
    }

    #[Test]
    public function inicio_encaminha_membros_pelo_evento_e_abertura_direta_sem_consumir_tentativa(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa(iniciar: false);
        $this->actingAs($bruno);
        $lobby = Livewire::test(MostrarMesa::class, ['id' => $mesa->id]);
        app(Plataforma::class)->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        $lobby->dispatch('mesa-atualizada.'.$mesa->id)->assertRedirect(route('mesas.jogo', $mesa->id));
        foreach ([$ana, $bruno] as $pessoa) {
            $this->actingAs($pessoa)->get(route('mesas.mostrar', $mesa->id))->assertRedirect(route('mesas.jogo', $mesa->id));
            $this->get(route('mesas.jogo', $mesa->id))->assertOk()->assertSee('data-tempo-real-mesa="jogo"', false)
                ->assertSee('Ordem dos jogadores')->assertSee('Placar da partida')->assertSee('Participantes da rodada');
        }
        $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id))->assertSee('É a sua vez')->assertSee('Iniciar sua vez')->assertSee('data-antes-de-iniciar="1"', false);
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $ana->identificadorNoDominio()));
    }

    #[Test]
    public function acesso_recusa_visitante_nao_verificado_pendente_nao_membro_e_saida(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa();
        $url = route('mesas.jogo', $mesa->id);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
        $pendente = User::factory()->create();
        $this->actingAs($pendente)->get($url)->assertNotFound();
        $p = app(Plataforma::class);
        $p->solicitarAmizade($ana->identificadorNoDominio(), $pendente->identificadorNoDominio());
        $p->aceitarAmizade($pendente->identificadorNoDominio(), $ana->identificadorNoDominio());
        $p->convidar($mesa->id, $ana->identificadorNoDominio(), $pendente->identificadorNoDominio());
        $this->get($url)->assertNotFound();
        $p->abandonarMesa($mesa->id, $bruno->identificadorNoDominio());
        $this->actingAs($bruno)->get($url)->assertNotFound();
    }

    #[Test]
    public function invisivel_nao_entra_e_atualizacao_remove_a_presenca_da_tela(): void
    {
        [$mesa, , $bruno] = $this->mesa();
        $this->actingAs($bruno);
        $painel = $this->painel($mesa, $bruno);
        $bruno->forceFill(['invisivel' => true])->save();
        $this->get(route('mesas.jogo', $mesa->id))->assertRedirect(route('dashboard'));
        $painel->dispatch('mesa-atualizada.'.$mesa->id)->assertRedirect(route('dashboard'))->assertDontSee('data-tempo-real-presenca', false);
    }

    #[Test]
    public function espectador_passa_ao_aviso_da_sua_vez_e_atualizacao_nao_reinicia_quem_joga(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa();
        $dados = $this->iniciar($mesa, $ana);
        $this->actingAs($ana);
        $jogando = $this->painel($mesa, $ana);
        app(Plataforma::class)->pausarPartida($mesa->id, $ana->identificadorNoDominio());
        $jogando->dispatch('mesa-atualizada.'.$mesa->id)->assertNoRedirect()->assertSee('Partida pausada');
        app(Plataforma::class)->despausarPartida($mesa->id, $ana->identificadorNoDominio());
        $this->actingAs($bruno)->get(route('mesas.jogo', $mesa->id))->assertSee('data-labirinto-assistir', false);
        $espectador = $this->painel($mesa, $bruno);
        $this->actingAs($ana)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 10000]))->assertOk();
        $this->actingAs($bruno);
        $espectador->dispatch('mesa-atualizada.'.$mesa->id)->assertRedirect(route('mesas.jogo', ['id' => $mesa->id, 'partida' => 1]));
        $this->get(route('mesas.jogo', $mesa->id))->assertSee('É a sua vez')->assertDontSee('data-labirinto-assistir', false);
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->tentativaDe(1, $bruno->identificadorNoDominio()));
    }

    #[Test]
    public function paralelo_mostra_resultados_pendentes_e_placar_sem_transmissao(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa(ModoExecucao::Paralelo);
        $dados = $this->iniciar($mesa, $ana);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 10000]))->assertOk();
        $this->get(route('mesas.jogo', $mesa->id))->assertOk()->assertSee('Já jogou')->assertSee('10,000 s')
            ->assertSee('Falta jogar: Bruno')->assertSee('Placar da partida')->assertDontSee('data-labirinto-assistir', false)
            ->assertDontSee('entrada-assistir')->assertDontSee('Iniciar sua vez');
        $this->actingAs($bruno)->get(route('mesas.jogo', $mesa->id))->assertSee('Iniciar sua vez')->assertDontSee('data-transmissao=', false);
    }

    #[Test]
    public function local_fica_na_ordem_e_inicia_somente_pelo_admin(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa(local: true);
        $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id))->assertSee('Jogar pela vez de Carla')
            ->assertSee('data-participante="'.app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->vezAtual().'"', false);
        $this->actingAs($bruno)->get(route('mesas.jogo', $mesa->id))->assertSee('Vez de Carla')->assertDontSee('Jogar pela vez de Carla');
        $dados = $this->iniciar($mesa, $ana);
        $pagina = $this->get(route('mesas.jogar', $mesa->id))->assertOk()
            ->assertSee('Carla está jogando')->assertSee('data-tempo-real-presenca', false);
        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $versaoMapa = $partida->mapaLabirinto()['id'] ?? 'legado';
        $pagina->assertSee('data-mesa="'.$mesa->id.'"', false)
            ->assertSee('data-participante="'.$partida->vezAtual().'"', false)
            ->assertSee('data-mapa-versao="'.$versaoMapa.'"', false);
        $this->actingAs($bruno)->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertSessionHasErrors('vez');
    }

    #[Test]
    public function membro_nao_executa_nenhuma_acao_do_admin_mesmo_forjando_chamada(): void
    {
        [$mesa, , $bruno] = $this->mesa();
        $this->actingAs($bruno)->get(route('mesas.jogo', $mesa->id))->assertDontSee('Ações do admin');
        foreach (['pausarPartida', 'despausarPartida', 'encerrarPartida', 'pularVez', 'admitirNaPartida', 'removerDaPartida'] as $acao) {
            $this->painel($mesa, $bruno)->call($acao, $bruno->identificadorNoDominio())->assertNotFound();
        }
    }

    #[Test]
    public function fim_leva_a_mesa_com_o_resultado_e_sua_mesa_nao_mostra_de_novo(): void
    {
        [$mesa, $ana, $bruno] = $this->terminarPartida();
        $comResultado = route('mesas.mostrar', ['id' => $mesa->id, 'resultado' => 1]);
        $this->get(route('mesas.jogo', $mesa->id))->assertRedirect($comResultado);
        $this->get($comResultado)->assertOk()->assertSee('data-test="modal-resultado-partida"', false)->assertSee('Fim da partida')
            ->assertSee('Resultado final')->assertSee('Ana venceu.')->assertSee('3 pontos')->assertSee('Continuar na mesa');
        $this->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertDontSee('data-test="modal-resultado-partida"', false);
        // Uma nova partida não muda o resultado da partida anterior nem o comprovante do retorno.
        app(Plataforma::class)->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', 120, null, null), $ana->identificadorNoDominio());
        $this->get(route('mesas.jogo', ['id' => $mesa->id, 'partida' => 1]))->assertRedirect($comResultado);
        $this->get($comResultado)->assertSee('Ana venceu.');
        $this->get(route('mesas.mostrar', ['id' => $mesa->id, 'resultado' => 2]))->assertOk()
            ->assertDontSee('data-test="modal-resultado-partida"', false);
    }

    #[Test]
    public function resultado_na_mesa_resiste_ao_tempo_real_e_so_sai_quando_dispensado(): void
    {
        [$mesa, $ana, $bruno] = $this->terminarPartida();
        $this->actingAs($bruno);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id, 'resultado' => 1])
            ->assertSee('data-test="modal-resultado-partida"', false)->assertSee('Ana venceu.');
        app(Plataforma::class)->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', 120, null, null), $ana->identificadorNoDominio());
        $tela->dispatch('mesa-atualizada.'.$mesa->id)->assertNoRedirect()
            ->assertSee('data-test="modal-resultado-partida"', false)->assertSee('Ana venceu.');
        $tela->call('dispensarResultado')->assertDontSee('data-test="modal-resultado-partida"', false);
    }

    #[Test]
    public function painel_revalida_saida_e_encerramento_da_mesa(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa();
        $this->actingAs($bruno);
        $painel = $this->painel($mesa, $bruno);
        app(Plataforma::class)->abandonarMesa($mesa->id, $bruno->identificadorNoDominio());
        $painel->dispatch('mesa-atualizada.'.$mesa->id)->assertRedirect(route('dashboard'));
        $this->actingAs($ana);
        $painel = $this->painel($mesa, $ana);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $painel->dispatch('mesa-atualizada.'.$mesa->id)->assertRedirect(route('mesas.mostrar', $mesa->id));
    }

    #[Test]
    public function erro_de_inicio_continua_visivel_apos_redirecionar_pela_mesa(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa();
        $pagina = $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id));
        $dados = collect(['jogada', 'partida', 'rodada', 'desempate'])->mapWithKeys(fn ($campo) => [$campo => $pagina->viewData($campo)])->all();
        $this->actingAs($bruno)->followingRedirects()->post(route('mesas.jogar.iniciar', $mesa->id), $dados)
            ->assertOk()->assertSee('Esta jogada não corresponde à sua vez. Volte para a mesa.');
    }

    private function painel(Mesa $mesa, User $pessoa)
    {
        $tela = TelaDoJogo::de(app(Plataforma::class)->mesa($mesa->id), $pessoa->identificadorNoDominio());

        return Livewire::test(PainelDoJogo::class, ['id' => $mesa->id, 'numero' => $tela->numero, 'chave' => $tela->chave]);
    }

    private function iniciar(Mesa $mesa, User $pessoa): array
    {
        $pagina = $this->actingAs($pessoa)->get(route('mesas.jogo', $mesa->id))->assertOk();
        $dados = collect(['jogada', 'partida', 'rodada', 'desempate'])->mapWithKeys(fn ($campo) => [$campo => $pagina->viewData($campo)])->all();
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect();

        return $dados;
    }

    /** Partida de uma rodada em que Ana vence Bruno. */
    private function terminarPartida(): array
    {
        [$mesa, $ana, $bruno] = $this->mesa(limite: 1);
        foreach ([$ana, $bruno] as $indice => $pessoa) {
            $dados = $this->iniciar($mesa, $pessoa);
            $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 10000 + 1000 * $indice]))
                ->assertOk()->assertJsonPath('destino', route('mesas.jogo', ['id' => $mesa->id, 'partida' => 1]));
        }

        return [$mesa, $ana, $bruno];
    }

    private function mesa(ModoExecucao $modo = ModoExecucao::Sequencial, bool $iniciar = true, bool $local = false, ?int $limite = null): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $p = app(Plataforma::class);
        $mesa = $p->criarMesa($ana->identificadorNoDominio());
        $p->naMesa($mesa->id, function (Mesa $m) use ($ana, $bruno, $modo, $iniciar, $local, $limite): void {
            $m->adicionarMembro($bruno->identificadorNoDominio());
            $idLocal = $local ? $m->cadastrarParticipanteLocal('Carla', 'carla@example.test', $ana->identificadorNoDominio()) : null;
            $partida = $m->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, $limite, $modo), $ana->identificadorNoDominio());
            if ($local) {
                $partida->reordenar([$idLocal, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio()], $ana->identificadorNoDominio());
            }
            if ($iniciar) {
                $partida->iniciar($ana->identificadorNoDominio());
            }
        });

        return [$mesa, $ana, $bruno];
    }
}
