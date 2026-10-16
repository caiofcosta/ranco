<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Amizade\ConvitesDeAmizade;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Livewire\Navegacao\BarraMobile;
use App\Models\User;
use App\Navegacao\ContextoMobile;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NavegacaoMobileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function convitesMudaramAtualizamABarraSemRecarregar(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['email' => 'bruno@exemplo.com']);
        $barra = Livewire::actingAs($bruno)->test(BarraMobile::class)->assertDontSee('mobile-nav-badge');

        app(ConvitesDeAmizade::class)->enviar($ana, 'bruno@exemplo.com');

        $barra->dispatch('convites-mudaram')->assertSee('1 pedido');
    }

    #[Test]
    public function areas_tem_rotas_proprias_protegidas_e_cinco_destinos(): void
    {
        foreach (['inicio', 'pedidos', 'jogar-juntos', 'mais'] as $area) {
            $this->get(route('mobile.'.$area))->assertRedirect(route('login'));
        }
        $user = User::factory()->create();
        foreach (['inicio', 'pedidos', 'jogar-juntos', 'mais'] as $area) {
            $response = $this->actingAs($user)->get(route('mobile.'.$area))->assertOk();
            $response->assertSee('data-mobile-area="'.$area.'"', false);
            $this->assertSame(5, substr_count($response->getContent(), 'data-mobile-destino='));
        }
        // Jogar solo reaproveita o catálogo existente; a barra se mantém com os cinco destinos.
        $solo = $this->actingAs($user)->get(route('jogar.index'))->assertOk();
        $this->assertSame(5, substr_count($solo->getContent(), 'data-mobile-destino='));
        $this->get(route('dashboard'))->assertSee('data-mobile-area="inicio"', false);
    }

    #[Test]
    public function inicio_apresenta_atalhos_sociais_sem_tom_e_destaque_pode_ser_desativado(): void
    {
        $usuario = User::factory()->create(['name' => 'Caio de Teste']);
        $this->actingAs($usuario);
        $resposta = $this->get(route('mobile.inicio'))->assertOk();
        $resposta->assertSee('Jogue com a galera')
            ->assertSee('Mesas de jogos')->assertSee('Amigos')
            ->assertSee('Jogar sozinho')->assertSee('Ver todos')
            ->assertSee('Labirinto em mesa')
            ->assertDontSee('data-mobile-tom', false);
        $this->assertSame(2, substr_count($resposta->getContent(), 'data-mobile-atalho='));
        $this->assertSame(1, substr_count($resposta->getContent(), 'data-mobile-destaque'));
        $this->get(route('mobile.pedidos'))->assertDontSee('data-mobile-tom', false)->assertDontSee('data-mobile-destaque', false);
        config(['home-mobile.destaque.ativo' => false]);
        $this->get(route('mobile.inicio'))->assertDontSee('data-mobile-destaque', false);
    }

    #[Test]
    public function distingue_administracao_de_participacao_e_limite_conta_so_as_administradas(): void
    {
        $user = User::factory()->create();
        $outro = User::factory()->create();
        $p = app(Plataforma::class);
        $p->criarMesa($user->identificadorNoDominio(), 'Minha mesa');
        for ($i = 0; $i < 4; $i++) {
            $mesa = $p->criarMesa($outro->identificadorNoDominio(), 'Convite '.$i);
            $p->naMesa($mesa->id, fn (Mesa $m) => $m->adicionarMembro($user->identificadorNoDominio()));
        }
        $contexto = app(ContextoMobile::class)->de($user);
        $this->assertCount(1, $contexto['administradas']);
        $this->assertCount(5, $contexto['participacoes']);
        // Participar em mesas de terceiros não consome o limite: só a mesa administrada conta.
        $this->assertSame(4, $contexto['vagas']);
        $this->assertSame(1, $contexto['total']);
        $this->assertSame('Administrador', $contexto['pendencias'][0]['tipo']);
    }

    #[Test]
    public function somente_acoes_da_pessoa_contam_como_pendencia_e_barra_atualiza_sem_redirecionar(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        $p = app(Plataforma::class);
        $mesa = $p->criarMesa($ana->identificadorNoDominio(), 'Rodada mobile');
        $p->naMesa($mesa->id, fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));
        $barra = Livewire::actingAs($bruno)->test(BarraMobile::class);
        $barra->assertDontSee('mobile-nav-badge');
        $p->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 120, null, null), $ana->identificadorNoDominio());
        $p->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        $contexto = app(ContextoMobile::class)->de($ana);
        $this->assertSame(1, $contexto['total']);
        $this->assertSame('Sua vez', $contexto['pendencias'][0]['tipo']);
        $this->assertSame(0, app(ContextoMobile::class)->de($bruno)['total']);
        $p->iniciarTentativa($mesa->id, $ana->identificadorNoDominio(), fn (Mesa $m) => [$m->partidaAtiva(), $ana->identificadorNoDominio()]);
        $this->assertSame($mesa->id, app(ContextoMobile::class)->de($ana)['jogando']['mesa']->id);
        $this->assertNull(app(ContextoMobile::class)->de($bruno)['jogando']);
        $barra->call('$refresh')->assertNoRedirect()->assertDontSee('mobile-nav-badge');
        // Turnos jogáveis não entram no badge de Pedidos (só convites de mesa e amizade), então a Ana não vê badge.
        Livewire::actingAs($ana)->test(BarraMobile::class)->assertDontSee('mobile-nav-badge')->call('$refresh')->assertNoRedirect();
    }

    #[Test]
    public function mais_mantem_acesso_aos_recursos_existentes_e_saida_por_post(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('mobile.mais'))->assertOk();
        foreach (['Amigos', 'Medalhas e troféus', 'Histórico', 'Perfil e conta', 'Configurações', 'Sair'] as $label) {
            $response->assertSee($label);
        }
        $response->assertSee(route('logout'), false)->assertSee('method="POST"', false);
    }
}
