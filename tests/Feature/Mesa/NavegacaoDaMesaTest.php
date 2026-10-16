<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Livewire\Mesa\MostrarMesa;
use App\Models\DismissaoDeModal;
use App\Models\ModalDeNotificacao;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NavegacaoDaMesaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
    }

    #[Test]
    public function cabecalho_mostra_nome_e_administrador(): void
    {
        [, $bruno, $mesa] = $this->mesaComDuasPessoas();

        $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('data-test="nome-da-mesa"', false)
            ->assertSee('Grupo')
            ->assertSee('Ana');
    }

    #[Test]
    public function no_celular_a_mesa_troca_caminho_e_barra_inferior_por_voltar_e_menu(): void
    {
        [, $bruno, $mesa] = $this->mesaComDuasPessoas();

        $resposta = $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertDontSee('data-mobile-breadcrumb', false)
            ->assertDontSee('data-mobile-nav', false)
            ->assertSee('data-mesa-rodape', false);
        $html = $this->xpath($resposta->getContent());

        $voltar = $html->query('//a[@data-tela-cheia-voltar]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $voltar);
        $this->assertSame(route('mobile.jogar-juntos'), $voltar->getAttribute('href'));
        $this->assertSame(1, $html->query('//*[@data-mobile-menu]')->length);
    }

    // -- Preparar mesa (admin) --

    #[Test]
    public function admin_ve_preparar_mesa_com_os_itens_do_fluxo_existente(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="nav-preparar"', false)
            ->assertSee('Preparar mesa')
            ->assertSee('Organize a mesa antes de começar!')
            ->assertSee('data-test="preparar-lugares"', false)
            ->assertSee('data-test="preparar-jogo"', false)
            ->assertSee('data-test="preparar-mesa"', false);
    }

    #[Test]
    public function participante_nao_ve_preparar_nem_controles_administrativos(): void
    {
        [, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($bruno);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertDontSee('data-test="nav-preparar"', false)
            ->assertDontSee('data-test="nav-finalizar"', false)
            ->assertDontSee('data-test="cancelar-mesa"', false)
            ->assertSee('data-test="nav-ajuda"', false);
    }

    // -- Centro da mesa: jogo x espera --

    #[Test]
    public function admin_sem_jogo_ve_escolher_jogo_e_participante_ve_aguardando(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();

        $this->actingAs($ana);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('Escolher jogo')
            ->assertDontSee('Aguardando o próximo jogo');

        $this->actingAs($bruno);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="aguardando-jogo"', false)
            ->assertSee('Aguardando o próximo jogo')
            ->assertSee('O administrador organiza a mesa e escolhe o próximo jogo.');
    }

    // -- Resultados, ranking e histórico (sem regressão) --

    #[Test]
    public function resultados_sem_partidas_mostram_estados_vazios(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="modal-resultados"', false)
            ->assertSee('Ainda não tem resultado por aqui.')
            ->assertSee('Ainda não tem nenhuma partida por aqui.');
    }

    #[Test]
    public function historico_lista_partida_sem_vencedor_sem_pontuar_no_ranking(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->encerrarUmaPartida($mesa->id, $ana);
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="historico-da-mesa"', false)
            ->assertSee('Partida 1 · Labirinto')
            ->assertSee('Sem vencedor')
            ->assertSee('Ainda não tem resultado por aqui.');
    }

    #[Test]
    public function o_rodape_traz_a_classificacao_e_a_modal_jogos_da_mesa(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-mesa-rodape', false)
            ->assertSee('data-test="classificacao-da-mesa"', false)
            ->assertSee('Classificação da mesa')
            ->assertSee('data-test="classificacao-sem-resultado"', false)
            ->assertSee('Ana')
            ->assertSee('Bruno')
            ->assertSeeInOrder(['data-test="modal-jogos-da-mesa"', 'Jogos da mesa', 'Nenhum jogo disputado ainda.'], false);
    }

    // -- Orientação por papel (Partes 2 e 3) --

    #[Test]
    public function admin_ve_orientacao_de_administrador_na_primeira_entrada(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="modal-orientacao"', false)
            ->assertSee('Bora preparar a mesa?')
            ->assertDontSee('Você entrou na mesa!');
    }

    #[Test]
    public function participante_ve_orientacao_de_participante_na_primeira_entrada(): void
    {
        [, $bruno, $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($bruno);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="modal-orientacao"', false)
            ->assertSee('Você entrou na mesa!')
            ->assertDontSee('Bora preparar a mesa?');
    }

    #[Test]
    public function orientacao_nao_aparece_quando_dispensada_com_nao_mostrar(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $modal = ModalDeNotificacao::where('tipo', 'onboarding_mesa_admin')->firstOrFail();
        DismissaoDeModal::create([
            'user_id' => $ana->id,
            'modal_de_notificacao_id' => $modal->id,
            'descartou_em' => now(),
            'nao_mostrar_novamente' => true,
        ]);
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertDontSee('data-test="modal-orientacao"', false);
    }

    #[Test]
    public function dispensar_orientacao_persiste_a_preferencia_por_papel(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $modal = ModalDeNotificacao::where('tipo', 'onboarding_mesa_admin')->firstOrFail();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('dispensarOrientacao', true);

        $this->assertDatabaseHas('dismissoes_de_modal', [
            'user_id' => $ana->id,
            'modal_de_notificacao_id' => $modal->id,
            'nao_mostrar_novamente' => true,
        ]);
    }

    #[Test]
    public function ajuda_mostra_orientacao_conforme_o_papel(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComDuasPessoas();

        $this->actingAs($ana);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('Como funciona a mesa')
            ->assertSee('Finalizar disputa');

        $this->actingAs($bruno);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('Como funciona a mesa')
            ->assertSee('Quem administra organiza os participantes');
    }

    // -- Modal Lugares --

    #[Test]
    public function lugares_orienta_sem_forcar_convite_e_conta_as_pessoas(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('Quantas pessoas vão jogar? Você também conta!')
            ->assertSee('Escolha os lugares da mesa. Depois, adicione quem está com você ou convide a galera.')
            ->assertSee('data-test="lugares-ja-tem"', false)
            ->assertSee('Já tem 2 pessoas na mesa.')
            ->assertDontSee('data-test="lugares-convite-pendente"', false);
    }

    #[Test]
    public function lugares_mostra_recado_de_convite_so_quando_ha_convite_pendente(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->solicitarAmizade($ana->identificadorNoDominio(), $carla->identificadorNoDominio());
        $plataforma->aceitarAmizade($carla->identificadorNoDominio(), $ana->identificadorNoDominio());
        $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $carla->identificadorNoDominio());
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="lugares-convite-pendente"', false)
            ->assertSee('Convite só ocupa lugar quando a pessoa entrar.');
    }

    // -- Cancelar e Finalizar (Partes 5 e 6) --

    #[Test]
    public function admin_cancela_pela_opcoes_quando_nao_ha_partida_concluida(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="cancelar-mesa"', false)
            ->assertSee('Essa mesa será cancelada e a galera não poderá continuar jogando nela.')
            ->assertSee('Continuar na mesa')
            ->assertSee('Sim, cancelar mesa')
            ->assertDontSee('data-test="nav-finalizar"', false);
    }

    #[Test]
    public function admin_ve_finalizar_disputa_quando_ha_partida_concluida(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->encerrarUmaPartida($mesa->id, $ana);
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="nav-finalizar"', false)
            ->assertSee('Finalizar disputa')
            ->assertSee('Encerrar e entregar os troféus')
            ->assertSee('data-test="modal-confirmar-finalizar"', false)
            ->assertSee('Finalizar a disputa?')
            ->assertDontSee('data-test="cancelar-mesa"', false);
    }

    #[Test]
    public function cancelar_mesa_sem_partida_volta_ao_dashboard_com_recado(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();

        $this->actingAs($ana)->post(route('mesas.encerrar', $mesa->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Mesa cancelada. Dessa vez não rolou!');
    }

    #[Test]
    public function finalizar_mesa_com_partida_segue_para_o_resumo(): void
    {
        [$ana, , $mesa] = $this->mesaComDuasPessoas();
        $this->encerrarUmaPartida($mesa->id, $ana);

        $this->actingAs($ana)->post(route('mesas.encerrar', $mesa->id))
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertViewIs('mesa.encerrada');
    }

    #[Test]
    public function participante_nao_encerra_a_mesa_pelo_backend(): void
    {
        [, $bruno, $mesa] = $this->mesaComDuasPessoas();

        $this->actingAs($bruno)->post(route('mesas.encerrar', $mesa->id))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(
            \App\Dominio\Mesa\SituacaoMesa::Aberta,
            app(Plataforma::class)->mesa($mesa->id)->situacao(),
        );
    }

    // -- Helpers --

    private function encerrarUmaPartida(string $mesaId, User $admin): void
    {
        $plataforma = app(Plataforma::class);
        $por = $admin->identificadorNoDominio();
        $plataforma->criarPartida($mesaId, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), $por);
        $plataforma->iniciarPartida($mesaId, $por);
        $plataforma->encerrarPartida($mesaId, $por);
    }

    /** @return array{User, User, Mesa} */
    private function mesaComDuasPessoas(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));

        return [$ana, $bruno, $mesa];
    }

    private function xpath(string $html): DOMXPath
    {
        $documento = new DOMDocument;
        @$documento->loadHTML($html);

        return new DOMXPath($documento);
    }
}
