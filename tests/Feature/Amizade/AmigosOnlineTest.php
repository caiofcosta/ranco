<?php

declare(strict_types=1);

namespace Tests\Feature\Amizade;

use App\Amizade\AmigosOnline;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Relogio;
use App\Livewire\Amizade\StatusDosAmigos;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class AmigosOnlineTest extends TestCase
{
    use RefreshDatabase;

    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relogio = new RelogioFalso(
            new DateTimeImmutable('2026-10-04T12:00:00+00:00'),
        );
        $this->app->instance(Relogio::class, $this->relogio);
    }

    #[Test]
    public function mostraSomenteAmizadesAceitasDaContaAutenticada(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $fabi = User::factory()->create(['name' => 'Fabi']);
        $bia = User::factory()->create(['name' => 'Bia']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $dani = User::factory()->create(['name' => 'Dani']);

        $this->amigar($ana, $fabi);
        $this->amigar($ana, $bia);
        $this->amigar($fabi, $carla);
        app(Plataforma::class)->solicitarAmizade((string) $dani->id, (string) $ana->id);
        app(AmigosOnline::class)->registrar($fabi, $this->relogio->agora());

        $this->actingAs($ana);
        Livewire::test(StatusDosAmigos::class)
            ->assertSee('1 online · 1 offline')
            ->assertSeeInOrder(['Fabi', 'online agora', 'Bia', 'ainda não apareceu'])
            ->assertDontSee('Carla')
            ->assertDontSee('Dani');
    }

    #[Test]
    public function ficaOfflineDepoisDeDoisMinutosSemAtividade(): void
    {
        $ana = User::factory()->create();
        $fabi = User::factory()->create(['name' => 'Fabi']);
        $this->amigar($ana, $fabi);
        app(AmigosOnline::class)->registrar($fabi, $this->relogio->agora());

        $this->actingAs($ana);
        $this->relogio->avancar(120);
        $componente = Livewire::test(StatusDosAmigos::class)->assertSee('1 online · 0 offline');

        $this->relogio->avancar(1);
        $componente->call('$refresh')
            ->assertSee('0 online · 1 offline')
            ->assertSee('visto há 2 min');
    }

    #[Test]
    public function bloqueioTiraAPessoaDoContadorDosDois(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $fabi = User::factory()->create(['name' => 'Fabi']);
        $this->amigar($ana, $fabi);

        $this->actingAs($ana);
        $daAna = Livewire::test(StatusDosAmigos::class)->assertSee('Fabi');

        $this->post(route('amigos.bloquear', (string) $fabi->id))->assertRedirect();

        $daAna->call('$refresh')
            ->assertDontSee('Fabi')
            ->assertSee('0 online · 0 offline');

        $this->actingAs($fabi);
        Livewire::test(StatusDosAmigos::class)
            ->assertDontSee('Ana')
            ->assertSee('Nenhuma amizade ainda.');
    }

    #[Test]
    public function componenteRegistraAtividadeAoAbrirEAtualizar(): void
    {
        $ana = User::factory()->create();
        $this->actingAs($ana);

        $componente = Livewire::test(StatusDosAmigos::class)
            ->assertSee('0 online')
            ->assertSee('0 offline');

        $primeiraAtividade = DB::table('users')
            ->where('id', $ana->id)
            ->value('visto_em');

        $this->assertNotNull($primeiraAtividade);

        $this->relogio->avancar(45);
        $componente->call('$refresh')->assertOk();

        $ultimaAtividade = DB::table('users')
            ->where('id', $ana->id)
            ->value('visto_em');

        $this->assertSame(
            45,
            (new DateTimeImmutable($ultimaAtividade))->getTimestamp()
                - (new DateTimeImmutable($primeiraAtividade))->getTimestamp(),
        );
    }

    #[Test]
    public function amizadeRemovidaSomeNaProximaAtualizacao(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $fabi = User::factory()->create(['name' => 'Fabi']);
        $this->amigar($ana, $fabi);
        $this->actingAs($ana);

        $componente = Livewire::test(StatusDosAmigos::class)
            ->assertSee('Fabi');

        app(Plataforma::class)->desfazerAmizade(
            (string) $ana->id,
            (string) $fabi->id,
        );

        $componente->call('$refresh')
            ->assertDontSee('Fabi')
            ->assertSee('Nenhuma amizade ainda.');
    }

    #[Test]
    public function exigeAutenticacaoEEmailConfirmado(): void
    {
        Livewire::test(StatusDosAmigos::class)->assertStatus(401);

        $usuario = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($usuario);

        Livewire::test(StatusDosAmigos::class)->assertStatus(403);
    }

    #[Test]
    public function contadorApareceNasPaginasComMenuLateral(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['dashboard', 'amigos.index', 'profile.edit'] as $rota) {
            $this->get(route($rota))
                ->assertOk()
                ->assertSeeLivewire(StatusDosAmigos::class);
        }
    }

    /**
     * O "online" depende de o StatusDosAmigos estar no layout de toda tela jogável. Protege a mesa,
     * o jogo da mesa e o Labirinto solo (escolher e jogar) contra uma saída acidental do layout.
     */
    #[Test]
    public function heartbeatCobreMesaJogoDaMesaELabirintoSolo(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $lobby = $plataforma->criarMesa($ana->identificadorNoDominio());
        $emJogo = $plataforma->criarMesa($ana->identificadorNoDominio());
        $plataforma->naMesa($emJogo->id, static function (Mesa $mesa) use ($ana, $bruno): void {
            $mesa->adicionarMembro($bruno->identificadorNoDominio());
            $partida = $mesa->novaPartida(
                CatalogoDeJogos::configuracao('labirinto', 120, null, null, ModoExecucao::Sequencial),
                $ana->identificadorNoDominio(),
            );
            $partida->iniciar($ana->identificadorNoDominio());
        });

        $this->actingAs($ana);
        $this->get(route('mesas.mostrar', $lobby->id))->assertOk()->assertSeeLivewire(StatusDosAmigos::class);
        $this->get(route('mesas.jogo', $emJogo->id))->assertOk()->assertSeeLivewire(StatusDosAmigos::class);
        $this->get(route('jogar.labirinto'))->assertOk()->assertSeeLivewire(StatusDosAmigos::class);

        $tentativa = $this->post(route('jogar.labirinto.iniciar'))->assertRedirect()->headers->get('Location');
        $this->get($tentativa)->assertOk()->assertSeeLivewire(StatusDosAmigos::class);
    }

    private function amigar(User $primeiro, User $segundo): void
    {
        app(Plataforma::class)->aceitarConviteDeAmizade(
            (string) $primeiro->id,
            (string) $segundo->id,
        );
    }
}
