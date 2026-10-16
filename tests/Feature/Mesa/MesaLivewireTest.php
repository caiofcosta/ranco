<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Livewire\Mesa\MostrarMesa;
use App\Livewire\Mesa\PainelDoJogo;
use App\Mesa\TelaDoJogo;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MesaLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
    }

    #[Test]
    public function pagina_monta_o_componente(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSeeLivewire(MostrarMesa::class);
    }

    #[Test]
    #[DataProvider('quantidadesDeJogadoresParaIniciar')]
    public function botao_iniciar_respeita_o_minimo_no_html(int $quantidade, bool $desabilitado): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        for ($indice = 1; $indice < $quantidade; $indice++) {
            $pessoa = User::factory()->create(['name' => 'Jogador '.$indice]);
            $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($pessoa->identificadorNoDominio()));
        }
        $this->actingAs($ana);

        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('criarPartida')
            ->assertHasNoErrors();

        $documento = new \DOMDocument;
        @$documento->loadHTML($tela->html());
        $consulta = new \DOMXPath($documento);
        $botoes = $consulta->query('//button[@data-test="iniciar-partida"]');
        $this->assertSame(1, $botoes->length);
        $botao = $botoes->item(0);
        $this->assertInstanceOf(\DOMElement::class, $botao);
        $this->assertSame($desabilitado, $botao->hasAttribute('disabled'));
        $this->assertStringNotContainsString('@disabled', $tela->html());
    }

    public static function quantidadesDeJogadoresParaIniciar(): array
    {
        return [
            'abaixo do minimo' => [1, true],
            'no minimo' => [2, false],
            'acima do minimo' => [3, false],
        ];
    }

    #[Test]
    public function sem_java_script_encerrar_passa_pela_confirmacao_antes_de_gravar(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), $ana->identificadorNoDominio());
        $plataforma->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        $this->actingAs($ana);

        $pagina = $this->get(route('mesas.jogo', $mesa->id))->assertOk();
        $documento = new \DOMDocument;
        @$documento->loadHTML($pagina->getContent());
        $consulta = new \DOMXPath($documento);
        $links = $consulta->query('//a[@data-test="encerrar-partida"]');
        $this->assertSame(1, $links->length);
        $link = $links->item(0);
        $this->assertSame(route('mesas.partida.encerrar', $mesa->id), $link->getAttribute('href'));
        $this->assertSame('encerrarPartida', $link->getAttribute('wire:click.prevent'));
        $this->assertSame('Encerrar agora termina a partida sem vencedor.', $link->getAttribute('wire:confirm'));
        $this->assertSame(0, $consulta->query('//form[@action="'.$link->getAttribute('href').'"]')->length);

        $this->get($link->getAttribute('href'))
            ->assertOk()
            ->assertSee('Encerrar agora termina a partida sem vencedor.')
            ->assertSee('data-test="confirmar-encerrar-partida"', false);
        $this->assertSame(SituacaoPartida::EmAndamento, $plataforma->mesa($mesa->id)->partidaAtiva()->situacao());

        $this->post($link->getAttribute('href'))
            ->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->assertNull($plataforma->mesa($mesa->id)->partidaAtiva());
        $this->assertNull($plataforma->mesa($mesa->id)->partidas()[0]->vencedor());
    }

    #[Test]
    public function admin_controla_a_partida_sem_sair_do_componente(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $plataforma = app(Plataforma::class);
        $this->actingAs($ana);

        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->set('prazo_vez_segundos', '')
            ->set('meta_pontos', '')
            ->set('limite_rodadas', '')
            ->call('criarPartida')
            ->assertHasNoErrors()
            ->assertSee('Jogo escolhido');

        $this->assertSame(120, $plataforma->mesa($mesa->id)->partidaAtiva()->configuracao()->prazoVezSegundos);
        $tela->call('iniciarPartida')->assertHasNoErrors()->assertRedirect(route('mesas.jogo', $mesa->id));
        $contexto = TelaDoJogo::de($plataforma->mesa($mesa->id), $ana->identificadorNoDominio());
        $tela = Livewire::test(PainelDoJogo::class, ['id' => $mesa->id, 'numero' => 1, 'chave' => $contexto->chave]);
        $this->assertSame(SituacaoPartida::EmAndamento, $plataforma->mesa($mesa->id)->partidaAtiva()->situacao());

        $tela->call('pausarPartida')->assertHasNoErrors()->assertSee('Partida pausada');
        $this->assertTrue($plataforma->mesa($mesa->id)->partidaAtiva()->pausada());
        $tela->call('despausarPartida')->assertHasNoErrors();
        $this->assertFalse($plataforma->mesa($mesa->id)->partidaAtiva()->pausada());

        $carla = User::factory()->create(['name' => 'Carla']);
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($carla->identificadorNoDominio()));
        $tela->dispatch('mesa-atualizada.'.$mesa->id)->assertSee('Carla');
        $tela->call('admitirNaPartida', $carla->identificadorNoDominio())->assertHasNoErrors();
        $this->assertTrue($plataforma->mesa($mesa->id)->partidaAtiva()->estaNaDisputa($carla->identificadorNoDominio()));

        $tela->call('pularVez', $ana->identificadorNoDominio())->assertHasNoErrors()->assertSee('A vez de Bruno passa em');
        $this->assertSame($bruno->identificadorNoDominio(), $plataforma->mesa($mesa->id)->partidaAtiva()->vezAtual());

        $tela->call('removerDaPartida', $carla->identificadorNoDominio())->assertHasNoErrors();
        $this->assertFalse($plataforma->mesa($mesa->id)->partidaAtiva()->estaNaDisputa($carla->identificadorNoDominio()));
        $this->assertTrue($plataforma->mesa($mesa->id)->membroAtivo($carla->identificadorNoDominio()));

        $tela->call('encerrarPartida')->assertHasNoErrors()->assertRedirect(route('mesas.jogo', ['id' => $mesa->id, 'partida' => 1]));
        $this->get(route('mesas.jogo', $mesa->id))->assertRedirect(route('mesas.mostrar', ['id' => $mesa->id, 'resultado' => 1]));
        $this->get(route('mesas.mostrar', ['id' => $mesa->id, 'resultado' => 1]))->assertSee('Fim da partida')
            ->assertSee('Partida encerrada sem vencedor.');
        $encerrada = $plataforma->mesa($mesa->id);
        $this->assertNull($encerrada->partidaAtiva());
        $this->assertNull($encerrada->partidas()[0]->vencedor());
    }

    #[Test]
    public function validacao_impede_criar_com_prazo_invalido(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->set('prazo_vez_segundos', 0)
            ->call('criarPartida')
            ->assertHasErrors(['prazo_vez_segundos']);

        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva());
    }

    #[Test]
    public function mostra_erro_do_dominio_quando_nao_atinge_o_minimo(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('criarPartida')
            ->call('iniciarPartida')
            ->assertHasErrors(['partida'])
            ->assertSee('A partida não atingiu o mínimo de jogadores.');

        $this->assertSame(SituacaoPartida::EmConfiguracao, app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->situacao());
    }

    #[Test]
    #[DataProvider('acoesDoAdmin')]
    public function membro_nao_pode_executar_acoes_do_admin(string $acao, array $argumentos): void
    {
        [, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($bruno);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call($acao, ...$argumentos)
            ->assertNotFound();

        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva());
    }

    public static function acoesDoAdmin(): array
    {
        return [
            ['criarPartida', []],
            ['salvarConfiguracao', []],
            ['abrirEscolhaDoJogo', []],
            ['iniciarPartida', []],
            ['pausarPartida', []],
            ['despausarPartida', []],
            ['pularVez', ['qualquer']],
            ['admitirNaPartida', ['qualquer']],
            ['removerDaPartida', ['qualquer']],
            ['encerrarPartida', []],
        ];
    }

    #[Test]
    public function visitante_e_nao_membro_nao_leem_a_mesa(): void
    {
        [, , $mesa] = $this->mesaComDuasPessoas();
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])->assertStatus(401);

        $this->actingAs(User::factory()->create());
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])->assertNotFound();
    }

    #[Test]
    public function exige_email_verificado(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $ana->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])->assertForbidden();
    }

    #[Test]
    public function identificador_da_mesa_nao_pode_ser_trocado_pelo_cliente(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $tela->set('id', 'outra-mesa');
    }

    #[Test]
    public function evento_rele_estado_e_revalida_participacao(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $plataforma = app(Plataforma::class);
        $this->actingAs($bruno);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id]);

        $plataforma->renomearMesa($mesa->id, 'Novo nome', $ana->identificadorNoDominio());
        $tela->dispatch('mesa-atualizada.'.$mesa->id)->assertSee('Novo nome');

        $plataforma->abandonarMesa($mesa->id, $bruno->identificadorNoDominio());
        $tela->dispatch('mesa-atualizada.'.$mesa->id)->assertNotFound();
    }

    #[Test]
    public function encerramento_da_mesa_redireciona_para_o_resumo_existente(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($bruno);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id]);

        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $tela->dispatch('mesa-atualizada.'.$mesa->id)
            ->assertRedirect(route('mesas.mostrar', $mesa->id));
    }

    #[Test]
    public function escolher_jogo_prepara_sem_iniciar_e_atualiza_o_centro_da_mesa(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('Nenhum jogo escolhido')
            ->assertSee('Qual vai ser?')
            ->assertDontSee('data-test="iniciar-partida"', false)
            ->call('criarPartida')
            ->assertHasNoErrors()
            ->assertDispatched('jogo-confirmado')
            ->assertSee('PRÓXIMA PARTIDA')
            ->assertSee('Pronto para começar')
            ->assertDontSee('Qual vai ser?');

        $this->assertSame(SituacaoPartida::EmConfiguracao, app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->situacao());
        $documento = new \DOMDocument;
        @$documento->loadHTML($tela->html());
        $consulta = new \DOMXPath($documento);
        $this->assertSame(1, $consulta->query('//*[@data-test="mesa-de-jogo"]//button[@data-test="iniciar-partida"]')->length);
        $tela->call('iniciarPartida')->assertHasNoErrors()->assertDontSee('data-test="preparacao-da-mesa"', false);
    }

    #[Test]
    public function membro_ve_jogo_escolhido_mas_nao_pode_iniciar_pela_tela(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();
        app(Plataforma::class)->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), $ana->identificadorNoDominio());
        $this->actingAs($bruno);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('Jogo escolhido')
            ->assertSee('O administrador inicia a partida.')
            ->assertDontSee('data-test="iniciar-partida"', false)
            ->assertDontSee('data-test="criar-partida"', false);
    }

    #[Test]
    public function edita_configuracao_sem_criar_ou_iniciar_outra_partida(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('criarPartida')->call('abrirEscolhaDoJogo')
            ->assertSet('prazo_vez_segundos', 120)
            ->set('prazo_vez_segundos', 90)->set('meta_pontos', '')->set('limite_rodadas', 3)
            ->call('salvarConfiguracao')->assertHasNoErrors()
            ->assertDispatched('jogo-confirmado')
            ->call('abrirEscolhaDoJogo')->assertSet('prazo_vez_segundos', 90)
            ->assertSet('limite_rodadas', 3)->assertSee('✓ Jogadores prontos');
        $plataforma = app(Plataforma::class);
        $this->assertCount(1, $plataforma->mesa($mesa->id)->partidas());
        $this->assertSame(SituacaoPartida::EmConfiguracao, $plataforma->mesa($mesa->id)->partidaAtiva()->situacao());
        $tela->call('iniciarPartida')->set('prazo_vez_segundos', 60)
            ->call('salvarConfiguracao')->assertRedirect(route('mesas.jogo', $mesa->id));
        $this->assertSame(90, $plataforma->mesa($mesa->id)->partidaAtiva()->configuracao()->prazoVezSegundos);
    }

    #[Test]
    public function lugares_persistem_limitam_cadeiras_sem_exigir_mesa_cheia(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('abrirLugares')->assertDispatched('escolher-lugares')
            ->set('quantidade_lugares', 4)->call('salvarLugares')
            ->assertHasNoErrors()->assertDispatched('lugares-confirmados')
            ->assertSee('2/4 jogadores')->assertSee('data-lugares="4"', false)
            ->call('criarPartida')->call('iniciarPartida')->assertHasNoErrors()
            ->set('quantidade_lugares', 5)->call('salvarLugares')->assertHasErrors('partida');
        $plataforma = app(Plataforma::class);
        $this->assertSame(4, $plataforma->mesa($mesa->id)->quantidadeLugares());
        $this->assertSame(SituacaoPartida::EmAndamento, $plataforma->mesa($mesa->id)->partidaAtiva()->situacao());
    }

    #[Test]
    public function somente_admin_pode_configurar_lugares_validos(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($bruno);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->set('quantidade_lugares', 4)->call('salvarLugares')->assertNotFound();
        $this->actingAs($ana);
        foreach ([1, 9, 'abc'] as $invalido) {
            Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
                ->set('quantidade_lugares', $invalido)->call('salvarLugares')
                ->assertHasErrors('quantidade_lugares');
        }
        foreach (range(2, 8) as $quantidade) {
            $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
                ->set('quantidade_lugares', $quantidade)->call('salvarLugares')->assertHasNoErrors();
            $this->assertSame($quantidade, substr_count($tela->html(), 'class="cadeira"'));
        }
    }

    #[Test]
    public function pontuacao_personalizada_persiste_pode_ser_revista_e_restaurada(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $plataforma = app(Plataforma::class);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->set('quantidade_lugares', 4)->call('salvarLugares')
            ->call('abrirEscolhaDoJogo')
            ->set('pontos_por_posicao', [10, 6, 3, 1])->call('criarPartida')->assertHasNoErrors()
            ->call('abrirEscolhaDoJogo')
            ->assertSet('pontos_por_posicao', [10, 6, 3, 1]);
        $this->assertSame([10, 6, 3, 1, 1, 1, 1, 1], $plataforma->mesa($mesa->id)->partidaAtiva()->configuracao()->pontosPorPosicao);
        $tela->set('pontos_por_posicao', [10, 11, 3, 1])->call('salvarConfiguracao')->assertHasErrors('partida');
        $this->assertSame([10, 6, 3, 1, 1, 1, 1, 1], $plataforma->mesa($mesa->id)->partidaAtiva()->configuracao()->pontosPorPosicao);
        $tela->set('pontos_por_posicao', [0, 0, 0, 0])->set('meta_pontos', 10)
            ->call('salvarConfiguracao')->assertHasErrors('partida');
        $tela->set('pontos_por_posicao', [3, 2, 1, 0])->call('salvarConfiguracao')->assertHasNoErrors();
        $this->assertSame([3, 2, 1], $plataforma->mesa($mesa->id)->partidaAtiva()->configuracao()->pontosPorPosicao);
    }

    #[Test]
    public function pontuacao_personalizada_valida_campos_e_tambem_funciona_por_http(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $plataforma = app(Plataforma::class);
        $plataforma->configurarLugares($mesa->id, 2, $ana->identificadorNoDominio());
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('abrirEscolhaDoJogo')
            ->assertSee('1º lugar')->assertSee('2º lugar')->assertSee('3º lugar')
            ->assertSee('Demais posições')->assertDontSee('Personalizar pontuação')->assertSet('pontos_por_posicao', [3, 2, 1, 0]);
        foreach ([[3], [3, 2, 1, -1], [3, 2, 1, 0.5], [3, 2, 1, 'abc']] as $invalida) {
            Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
                ->set('pontos_por_posicao', $invalida)
                ->call('criarPartida')->assertHasErrors();
        }
        $this->post(route('mesas.partidas.criar', $mesa->id), [
            'personalizar_pontos' => '1', 'pontos_por_posicao' => [7, 2, 1, 0],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([7, 2, 1, 0, 0, 0, 0, 0], $plataforma->mesa($mesa->id)->partidaAtiva()->configuracao()->pontosPorPosicao);
        $plataforma->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertRedirect(route('mesas.jogo', $mesa->id));
        $this->assertSame([7, 2, 1, 0, 0, 0, 0, 0], $plataforma->mesa($mesa->id)->partidaAtiva()->configuracao()->pontosPorPosicao);
    }

    #[Test]
    public function novos_jogos_gravam_a_meta_padrao_de_pontos_sem_limite_de_rodadas_e_encerram_na_meta(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSet('meta_pontos', 5)->assertSet('limite_rodadas', null)
            ->call('abrirEscolhaDoJogo')->assertSet('meta_pontos', 5)->assertSet('limite_rodadas', null)
            ->call('criarPartida')->assertHasNoErrors();
        $p = app(Plataforma::class);
        $config = $p->mesa($mesa->id)->partidaAtiva()->configuracao();
        self::assertSame(5, $config->metaPontos);
        self::assertNull($config->limiteRodadas);
        $p->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        // Ana vence todas as rodadas (3 pontos cada): a meta de 5 é atingida na 2ª.
        for ($r = 1; $r <= 10 && $p->mesa($mesa->id)->partidaAtiva() !== null; $r++) {
            $p->naMesa($mesa->id, function (Mesa $m) use ($ana, $bruno): void {
                $partida = $m->partidaAtiva();
                foreach ([$ana, $bruno] as $i => $pessoa) {
                    $id = $pessoa->identificadorNoDominio();
                    $partida->iniciarTentativa($id);
                    $partida->concluirTentativa($id, 20000 + 1000 * $i);
                }
            });
        }
        $salva = $p->mesa($mesa->id);
        self::assertNull($salva->partidaAtiva());
        self::assertSame(2, $salva->partidas()[0]->rodadaAtual());
        self::assertSame($ana->identificadorNoDominio(), $salva->partidas()[0]->vencedor());
    }

    #[Test]
    public function padroes_podem_ser_editados_ou_apagados_sem_reaparecer_na_edicao(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $tela = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->set('meta_pontos', 20)->set('limite_rodadas', '')->call('criarPartida')->assertHasNoErrors()
            ->call('abrirEscolhaDoJogo')->assertSet('meta_pontos', 20)->assertSet('limite_rodadas', null)
            ->set('meta_pontos', '')->set('limite_rodadas', 8)->call('salvarConfiguracao')->assertHasNoErrors()
            ->call('abrirEscolhaDoJogo')->assertSet('meta_pontos', null)->assertSet('limite_rodadas', 8)
            ->set('limite_rodadas', '')->call('salvarConfiguracao')->assertHasNoErrors()
            ->call('abrirEscolhaDoJogo')->assertSet('meta_pontos', null)->assertSet('limite_rodadas', null);
        $config = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->configuracao();
        self::assertNull($config->metaPontos);
        self::assertNull($config->limiteRodadas);
    }

    #[Test]
    public function formulario_http_tem_valores_reais_e_post_sem_campos_usa_meta_padrao(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        $html = $this->get(route('mesas.mostrar', $mesa->id))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        self::assertSame('5', $xpath->query('//input[@name="meta_pontos"]')->item(0)->getAttribute('value'));
        self::assertSame('', $xpath->query('//input[@name="limite_rodadas"]')->item(0)->getAttribute('value'));
        $this->post(route('mesas.partidas.criar', $mesa->id), [])->assertSessionHasNoErrors();
        $config = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->configuracao();
        self::assertSame(5, $config->metaPontos);
        self::assertNull($config->limiteRodadas);
        [$outra, , $m2] = $this->mesaComDuasPessoas();
        $this->actingAs($outra)->post(route('mesas.partidas.criar', $m2->id), ['meta_pontos' => '', 'limite_rodadas' => ''])->assertSessionHasNoErrors();
        $config = app(Plataforma::class)->mesa($m2->id)->partidaAtiva()->configuracao();
        self::assertNull($config->metaPontos);
        self::assertNull($config->limiteRodadas);
    }

    #[Test]
    public function limite_de_rodadas_encerra_na_quinta_rodada_se_ninguem_atingir_a_meta(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->set('pontos_por_posicao', [1, 0, 0, 0])
            ->set('meta_pontos', '')
            ->set('limite_rodadas', 5)
            ->call('criarPartida')->assertHasNoErrors();
        $p = app(Plataforma::class);
        $configuracao = $p->mesa($mesa->id)->partidaAtiva()->configuracao();
        self::assertNull($configuracao->metaPontos);
        self::assertSame(5, $configuracao->limiteRodadas);
        $p->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        for ($r = 1; $r <= 5; $r++) {
            $p->naMesa($mesa->id, function (Mesa $m) use ($ana, $bruno): void {
                $partida = $m->partidaAtiva();
                foreach ([$ana, $bruno] as $i => $pessoa) {
                    $id = $pessoa->identificadorNoDominio();
                    $partida->iniciarTentativa($id);
                    $partida->concluirTentativa($id, 20000 + 1000 * $i);
                }
            });
        }
        $salva = $p->mesa($mesa->id);
        self::assertNull($salva->partidaAtiva());
        self::assertSame(5, $salva->partidas()[0]->rodadaAtual());
        self::assertSame(5, $salva->partidas()[0]->totalDe($ana->identificadorNoDominio()));
    }

    /** @return array{User, User, Mesa} */
    #[Test]
    public function post_com_meta_negativa_nao_cria_a_partida(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        $this->post(route('mesas.partidas.criar', $mesa->id), ['meta_pontos' => '-3', 'limite_rodadas' => ''])
            ->assertSessionHasErrors('meta_pontos');
        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva());
    }

    private function mesaComDuasPessoas(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));

        return [$ana, $bruno, $mesa];
    }
}
