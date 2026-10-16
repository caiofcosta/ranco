<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Mesa\PartidaNaMesa;
use App\Models\Jogo;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PartidaNaMesaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
    }

    #[Test]
    public function criar_partida_usa_labirinto_e_os_membros_da_mesa(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComAnaEBruno();
        $this->actingAs($ana);

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Escolher jogo', false)
            ->assertSee('Labirinto', false)
            ->assertSee('Padrão: 120', false);

        $this->post(route('mesas.partidas.criar', $mesa->id), [])
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva();
        $this->assertNotNull($partida);
        $this->assertSame('labirinto', $partida->configuracao()->jogo);
        $this->assertEqualsCanonicalizing(
            [$ana->identificadorNoDominio(), $bruno->identificadorNoDominio()],
            $partida->ordem(),
        );

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Jogo escolhido', false)
            ->assertSee('Ana', false)
            ->assertSee('Bruno', false)
            ->assertSee('Iniciar partida', false);
    }

    #[Test]
    public function so_admin_cria_a_partida(): void
    {
        ['bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComAnaEBruno();

        $this->actingAs($bruno)
            ->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertDontSee('data-test="criar-partida"', false);

        $this->actingAs($bruno)
            ->post(route('mesas.partidas.criar', $mesa->id), [])
            ->assertNotFound();
    }

    #[Test]
    public function iniciar_respeita_o_minimo_eo_erro_fica_na_tela(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $this->actingAs($ana);

        $this->post(route('mesas.partidas.criar', $mesa->id), [])->assertRedirect();
        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('1/8 jogadores', false)
            ->assertSee('Aguardando jogadores', false);

        $this->post(route('mesas.partida.iniciar', $mesa->id))
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('A partida não atingiu o mínimo de jogadores.', false);
        $this->assertSame(
            SituacaoPartida::EmConfiguracao,
            app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()?->situacao(),
        );
    }

    #[Test]
    public function jogar_minha_vez_aparece_so_para_quem_esta_na_vez(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();

        $this->actingAs($ana)
            ->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Vez de Ana', false)
            ->assertSee('Iniciar sua vez')
            ->assertDontSee('Jogar pela vez de')
            ->assertSee('data-test="jogar-minha-vez"', false)
            ->assertSee('/mesas/'.$mesa->id.'/jogar', false);

        $this->actingAs($bruno)
            ->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Vez de Ana', false)
            ->assertDontSee('data-test="jogar-minha-vez"', false);
    }

    #[Test]
    public function partida_na_configuracao_nao_comeca_depois_de_inativar_o_jogo(): void
    {
        ['ana' => $ana, 'mesa' => $mesa] = $this->mesaComAnaEBruno();
        $this->actingAs($ana);
        $this->post(route('mesas.partidas.criar', $mesa->id), [])->assertRedirect();
        Jogo::query()->whereKey(CatalogoDeJogos::LABIRINTO)->update(['ativo' => false]);

        $this->post(route('mesas.partida.iniciar', $mesa->id))
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->assertSame(
            SituacaoPartida::EmConfiguracao,
            app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()?->situacao(),
        );
        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Este jogo não está disponível.');
    }

    #[Test]
    public function partida_em_andamento_continua_acessivel_se_o_jogo_for_inativado(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();
        Jogo::query()->whereKey(CatalogoDeJogos::LABIRINTO)->update(['ativo' => false]);

        $this->actingAs($ana)
            ->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Vez de Ana', false)
            ->assertSee('data-test="jogar-minha-vez"', false);

        $this->actingAs($bruno)
            ->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Vez de Ana', false)
            ->assertDontSee('data-test="jogar-minha-vez"', false);
    }

    #[Test]
    public function pausar_e_despausar_aparecem_na_tela(): void
    {
        ['ana' => $ana, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();
        $this->actingAs($ana);

        $this->post(route('mesas.partida.pausar', $mesa->id))
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Partida pausada', false)
            ->assertDontSee('data-test="jogar-minha-vez"', false)
            ->assertSee('data-test="despausar-partida"', false);

        $this->assertTrue(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()?->pausada());

        $this->post(route('mesas.partida.despausar', $mesa->id))->assertRedirect();
        $this->assertFalse(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()?->pausada());
        $this->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('data-test="jogar-minha-vez"', false);
    }

    #[Test]
    public function pular_vez_passa_para_o_proximo(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();
        $this->actingAs($ana);

        $this->post(route('mesas.partida.pular', $mesa->id), [
            'participante' => $ana->identificadorNoDominio(),
        ])->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->assertSame(
            $bruno->identificadorNoDominio(),
            app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()?->vezAtual(),
        );

        $this->actingAs($bruno)
            ->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Vez de Bruno', false)
            ->assertSee('data-test="jogar-minha-vez"', false);
    }

    #[Test]
    public function admitir_quem_entrou_depois_aparece_no_placar(): void
    {
        ['ana' => $ana, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();
        $carla = User::factory()->create(['name' => 'Carla']);
        app(Plataforma::class)->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($carla->identificadorNoDominio()));
        $this->actingAs($ana);

        $this->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Carla', false)
            ->assertSee('data-test="admitir-na-partida"', false);

        $this->post(route('mesas.partida.admitir', $mesa->id), [
            'participante' => $carla->identificadorNoDominio(),
        ])->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->assertTrue(
            app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()?->estaNaDisputa($carla->identificadorNoDominio()),
        );
        $this->get(route('mesas.jogo', $mesa->id))
            ->assertOk()
            ->assertSee('Carla', false)
            ->assertDontSee('data-test="admitir-na-partida"', false);
    }

    #[Test]
    public function remover_da_partida_mantem_na_mesa(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();
        $this->actingAs($ana);

        $this->post(route('mesas.partida.remover', $mesa->id), [
            'participante' => $bruno->identificadorNoDominio(),
        ])->assertRedirect(route('mesas.mostrar', $mesa->id));

        $mesa = app(Plataforma::class)->mesa($mesa->id);
        $partida = $mesa->partidaAtiva() ?? $mesa->partidas()[array_key_last($mesa->partidas())];
        $this->assertFalse($partida->estaNaDisputa($bruno->identificadorNoDominio()));
        $this->assertTrue($mesa->membroAtivo($bruno->identificadorNoDominio()));

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Bruno', false)
            ->assertSee('Removido', false);
    }

    #[Test]
    public function encerrar_partida_fica_sem_vencedor_e_permite_nova(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();
        $this->actingAs($ana);

        $this->get(route('mesas.partida.encerrar', $mesa->id))
            ->assertOk()
            ->assertSee('Encerrar agora termina a partida sem vencedor.', false);

        $this->post(route('mesas.partida.encerrar', $mesa->id))
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $mesa = app(Plataforma::class)->mesa($mesa->id);
        $this->assertNull($mesa->partidaAtiva());
        $this->assertNull($mesa->partidas()[0]->vencedor());
        $this->assertTrue($mesa->membroAtivo($bruno->identificadorNoDominio()));

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Partida encerrada', false)
            ->assertSee('Escolher jogo', false);

        $this->post(route('mesas.partidas.criar', $mesa->id), [])->assertRedirect();
        $this->assertNotNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva());
    }

    #[Test]
    public function somente_admin_ve_o_botao_para_jogar_pelo_local(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComAnaEBruno();
        $local = null;
        app(Plataforma::class)->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $bruno, &$local): void {
            $local = $mesa->cadastrarParticipanteLocal('Carla', 'carla@example.test', $ana->identificadorNoDominio());
            $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, null), $ana->identificadorNoDominio());
            $partida->iniciar($ana->identificadorNoDominio());
            $partida->pularVez($ana->identificadorNoDominio(), $ana->identificadorNoDominio());
            $partida->pularVez($bruno->identificadorNoDominio(), $ana->identificadorNoDominio());
        });
        $mesa = app(Plataforma::class)->mesa($mesa->id);
        $tela = PartidaNaMesa::de($mesa, $ana->identificadorNoDominio());
        $this->assertTrue($tela->podeJogarAVez);
        $this->assertTrue($tela->vezDeParticipanteLocal);
        $this->assertSame($local, $tela->vezId);
        $this->assertSame('Carla', $tela->vezNome);
        $this->assertSame(route('mesas.jogar', $mesa->id), $tela->urlJogar);
        $this->assertFalse(PartidaNaMesa::de($mesa, $bruno->identificadorNoDominio())->podeJogarAVez);
        $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id))->assertOk()
            ->assertSee('Jogar pela vez de Carla')->assertDontSee('Jogar minha vez');
        $this->actingAs($bruno)->get(route('mesas.jogo', $mesa->id))->assertOk()
            ->assertDontSee('data-test="jogar-minha-vez"', false);
        app(Plataforma::class)->pausarPartida($mesa->id, $ana->identificadorNoDominio());
        $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id))->assertOk()
            ->assertDontSee('Jogar pela vez de Carla');
    }

    #[Test]
    public function admin_nao_ve_o_botao_na_vez_de_outra_conta(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComPartidaEmAndamento();
        app(Plataforma::class)->pularVez($mesa->id, $ana->identificadorNoDominio(), $ana->identificadorNoDominio());
        $tela = PartidaNaMesa::de(app(Plataforma::class)->mesa($mesa->id), $ana->identificadorNoDominio());
        $this->assertFalse($tela->podeJogarAVez);
        $this->assertFalse($tela->vezDeParticipanteLocal);
        $this->assertNull($tela->urlJogar);
        $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id))->assertOk()
            ->assertSee('Vez de Bruno')->assertDontSee('data-test="jogar-minha-vez"', false);
    }

    /** @return array{ana: User, bruno: User, mesa: Mesa} */
    private function mesaComAnaEBruno(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));

        return compact('ana', 'bruno', 'mesa');
    }

    /** @return array{ana: User, bruno: User, mesa: Mesa} */
    private function mesaComPartidaEmAndamento(): array
    {
        $dados = $this->mesaComAnaEBruno();
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida(
            $dados['mesa']->id,
            CatalogoDeJogos::configuracao('labirinto', null, null, null),
            $dados['ana']->identificadorNoDominio(),
        );
        $plataforma->iniciarPartida($dados['mesa']->id, $dados['ana']->identificadorNoDominio());

        return $dados;
    }
}
