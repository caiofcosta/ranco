<?php

declare(strict_types=1);

namespace Tests\Feature\Conquista;

use App\Conquista\ConcedeTrofeuDaMesa;
use App\Dominio\Conquista\ConcessorDeMedalhas;
use App\Dominio\Conquista\ConcessorDeTrofeus;
use App\Dominio\Conquista\RepositorioDeTrofeus;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Plataforma\RepositorioDeMesas;
use App\Dominio\Relogio;
use App\Mesa\RankingDaMesa;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class TrofeusDaMesaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function mesaAbertaNaoPremiaEEncerramentoConcedeVitoriaUmaVez(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        $this->assertSame([], $this->trofeus($mesa));

        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $antes = $this->trofeus($mesa);
        $this->assertCount(2, $antes);
        $this->assertSame($ana->identificadorNoDominio(), $antes[0]->pessoa);
        $this->assertSame('vitoria', $antes[0]->tipo);
        $this->assertSame($bruno->identificadorNoDominio(), $antes[1]->pessoa);
        $this->assertSame('segundo', $antes[1]->tipo);
        $this->assertContains($antes[0]->frase, [
            'Levou a mesa. O resto da turma já está inventando desculpa.',
            'Primeiro lugar da mesa inteira. Alguém precisava, e foi você.',
            'A mesa acabou, e o topo é seu. Vai ser difícil ser modesto com isso.',
        ]);
        $this->assertStringNotContainsString('{', (string) $antes[0]->frase);
        app(ConcessorDeTrofeus::class)->concederPendentes(app(Plataforma::class)->mesa($mesa->id));
        app(Plataforma::class)->mesa($mesa->id);
        $this->assertEquals($antes, $this->trofeus($mesa));
    }

    #[Test]
    public function empateNoTopoConcedeUmTrofeuParaCadaPessoa(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        $this->vencer($mesa, $bruno->identificadorNoDominio());
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        app(Plataforma::class)->mesa($mesa->id);
        $trofeus = $this->trofeus($mesa);
        $this->assertCount(2, $trofeus);
        $this->assertSame(['empate', 'empate'], array_column($trofeus, 'tipo'));
        $this->assertEqualsCanonicalizing([$ana->identificadorNoDominio(), $bruno->identificadorNoDominio()], array_column($trofeus, 'pessoa'));
        $this->assertSame($trofeus[0]->concedido_em, $trofeus[1]->concedido_em);

        $resposta = $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))->assertOk();
        $this->assertCount(2, $resposta->viewData('trofeus'));
        $this->assertSame(2, substr_count($resposta->getContent(), 'FORÇA EM DOBRO'));
    }

    #[Test]
    public function mesaVaziaOuComPartidaSemVencedorNaoPremia(): void
    {
        foreach ([false, true] as $comPartida) {
            [$mesa, $ana] = $this->prepararMesa();
            if ($comPartida) {
                $this->abrirPartida($mesa);
            }
            app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
            $this->assertSame([], $this->trofeus($mesa));
            $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertDontSee('trofeu-da-mesa');
        }
    }

    #[Test]
    public function abandonoDoAdminTambemConcedeOTrofeu(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        app(Plataforma::class)->abandonarMesa($mesa->id, $ana->identificadorNoDominio());
        $this->assertSame($ana->identificadorNoDominio(), $this->trofeus($mesa)[0]->pessoa);
    }

    #[Test]
    public function quemSaiuOuFoiRemovidoContinuaNoRankingEPodeGanhar(): void
    {
        foreach (['abandono', 'remocao'] as $saida) {
            [$mesa, $ana, $bruno] = $this->prepararMesa();
            $this->vencer($mesa, $bruno->identificadorNoDominio());
            app(Plataforma::class)->naMesa($mesa->id, static function (Mesa $mesa) use ($ana, $bruno, $saida): void {
                if ($saida === 'abandono') {
                    $mesa->abandonar($bruno->identificadorNoDominio());
                } else {
                    $mesa->removerDaMesa($bruno->identificadorNoDominio(), $ana->identificadorNoDominio());
                }
                $mesa->encerrar($ana->identificadorNoDominio());
            });
            $ranking = RankingDaMesa::de(app(Plataforma::class)->mesa($mesa->id))->linhas;
            $this->assertSame(['id' => $bruno->identificadorNoDominio(), 'nome' => 'Bruno', 'pontos' => 3], $ranking[0]);
            $this->assertSame($bruno->identificadorNoDominio(), $this->trofeus($mesa)[0]->pessoa);
            $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertSee('trofeu-da-mesa');
        }
    }

    #[Test]
    public function nomeGravadoNaoMudaQuandoONomeDaMesaMudaNaPersistencia(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $antes = $this->trofeus($mesa);
        $repositorio = app(RepositorioDeMesas::class);
        $gravada = $repositorio->buscar($mesa->id);
        // A API proibe renomear mesa encerrada; simulamos uma alteracao posterior na persistencia.
        $gravada->__unserialize(['nome' => 'Outro nome']);
        $repositorio->salvar($gravada);
        app(Plataforma::class)->mesa($mesa->id);
        $this->assertEquals($antes, $this->trofeus($mesa));
        $this->assertSame('Grupo', app(RepositorioDeTrofeus::class)->dasPessoas([$ana->identificadorNoDominio()])[0]['mesa']);
    }

    #[Test]
    public function falhaNaConcessaoNaoDesfazAMesaETentaNovamenteNaLeitura(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        $real = app(ConcedeTrofeuDaMesa::class);
        $tentativas = 0;
        $nivelDaTransacaoDoTeste = DB::transactionLevel();
        $concessor = Mockery::mock(ConcessorDeTrofeus::class);
        $concessor->shouldReceive('concederPendentes')->twice()->andReturnUsing(
            function (Mesa $recebida) use ($real, &$tentativas, $nivelDaTransacaoDoTeste): void {
                $this->assertSame($nivelDaTransacaoDoTeste, DB::transactionLevel());
                $this->assertSame(SituacaoMesa::Encerrada, app(RepositorioDeMesas::class)->buscar($recebida->id)->situacao());
                if (++$tentativas === 1) {
                    throw new RuntimeException('Falha simulada na concessao do trofeu.');
                }
                $real->concederPendentes($recebida);
            },
        );
        $this->app->instance(ConcessorDeTrofeus::class, $concessor);
        $this->app->forgetInstance(Plataforma::class);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $this->assertSame([], $this->trofeus($mesa));
        $this->assertSame(SituacaoMesa::Encerrada, app(Plataforma::class)->mesa($mesa->id)->situacao());
        $this->assertCount(2, $this->trofeus($mesa));
    }

    #[Test]
    public function falhaDeMedalhaNaoImpedeAConcessaoDoTrofeu(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        $medalhas = Mockery::mock(ConcessorDeMedalhas::class);
        $medalhas->shouldReceive('concederPendentes')->once()->andThrow(new RuntimeException('Falha simulada de medalha.'));
        $this->app->instance(ConcessorDeMedalhas::class, $medalhas);
        $this->app->forgetInstance(Plataforma::class);

        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $this->assertCount(2, $this->trofeus($mesa));
        $this->assertSame(SituacaoMesa::Encerrada, app(RepositorioDeMesas::class)->buscar($mesa->id)->situacao());
    }

    #[Test]
    public function trofeuLocalFicaGuardadoEApareceApenasDepoisDoReconhecimento(): void
    {
        [$mesa, $admin] = $this->prepararMesa();
        $pessoa = User::factory()->create();
        $plataforma = app(Plataforma::class);
        $local = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Pessoa local', $pessoa->email, $admin->identificadorNoDominio());
        $this->vencer($mesa, $local);
        $plataforma->encerrarMesa($mesa->id, $admin->identificadorNoDominio());
        $this->assertSame($local, $this->trofeus($mesa)[0]->pessoa);
        $this->actingAs($pessoa)->get(route('perfil.mostrar'))->assertOk()->assertDontSee('Troféus');
        $this->actingAs($admin)->get(route('perfil.mostrar'))->assertOk()->assertDontSee('VITÓRIA');
        $this->actingAs($admin)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertSee('Pessoa local')->assertSee('trofeu-da-mesa');
        $plataforma->reconhecerParticipacaoLocal($local, $pessoa->identificadorNoDominio(), $pessoa->email);
        $this->actingAs($pessoa)->get(route('perfil.mostrar'))->assertOk()->assertSee('trofeu-do-perfil');
        $this->assertSame($local, $this->trofeus($mesa)[0]->pessoa);
    }

    #[Test]
    public function perfilProprioEDeAmigoMostramTrofeuComDataDeBrasilia(): void
    {
        $this->app->instance(Relogio::class, new RelogioFalso(new DateTimeImmutable('2026-10-04T01:30:00.123Z')));
        $this->app->forgetInstance(ConcessorDeTrofeus::class);
        $this->app->forgetInstance(Plataforma::class);
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        $this->app->instance(Relogio::class, new RelogioFalso(new DateTimeImmutable('2026-10-04T15:00:00.000Z')));
        $this->app->forgetInstance(ConcessorDeTrofeus::class);
        $this->app->forgetInstance(Plataforma::class);
        $plataforma = app(Plataforma::class);
        $plataforma->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $this->assertSame('2026-10-04 15:00:00.000', $this->trofeus($mesa)[0]->concedido_em);
        $this->assertSame('2026-10-04 01:30:00.123', $this->trofeus($mesa)[0]->mesa_criada_em);
        $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertSeeInOrder(['Medalhas', 'Troféus', 'VITÓRIA', 'Grupo', '03/10/2026'])
            ->assertDontSee('04/10/2026');
        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $this->actingAs($bruno)->get(route('perfil.amigo', $ana->id))->assertOk()
            ->assertSee('trofeu-do-perfil')->assertSee('03/10/2026')->assertDontSee('04/10/2026')->assertDontSee($ana->email);
        $this->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertSee('trofeu-da-mesa')->assertSee('03/10/2026')->assertDontSee('04/10/2026');
    }

    #[Test]
    public function naoAmigoEContaInexistenteContinuamComOMesmo403SemConsultarTrofeus(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $this->vencer($mesa, $ana->identificadorNoDominio());
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $repositorio = Mockery::mock(RepositorioDeTrofeus::class);
        $repositorio->shouldNotReceive('dasPessoas');
        $this->app->instance(RepositorioDeTrofeus::class, $repositorio);
        $resposta = $this->actingAs($bruno)->get(route('perfil.amigo', $ana->id))
            ->assertForbidden()->assertDontSee('Troféus')->assertDontSee('trofeu-do-perfil');
        $inexistente = $this->get(route('perfil.amigo', (string) (User::query()->max('id') + 1000)))->assertForbidden();
        // O Vite nao repete os preloads de fontes entre requisicoes na mesma aplicacao de teste.
        // Livewire gera ids próprios a cada requisição; o resto do HTML precisa ser idêntico.
        $semPreCarregamento = static fn (string $html): string => preg_replace(
            ['/wire:(snapshot|id|key)="[^"]*"/', "/fluxModal\\('([^']*)', '[^']*'\\)/"],
            ['wire:$1=""', "fluxModal('$1', '')"],
            implode("\n", array_filter(
                explode("\n", $html),
                static fn (string $linha): bool => ! str_contains($linha, 'rel="preload"'),
            )),
        );
        $this->assertSame($semPreCarregamento($resposta->getContent()), $semPreCarregamento($inexistente->getContent()));
        $this->assertSame($resposta->headers->get('Cache-Control'), $inexistente->headers->get('Cache-Control'));
    }

    #[Test]
    public function perfilOrdenaPorDataEIdentidadeEEscapaNomeGravado(): void
    {
        $ana = User::factory()->create();
        $repositorio = app(RepositorioDeTrofeus::class);
        $plataforma = app(Plataforma::class);
        $nomes = ['Antiga', 'Recente', '<script>alert("mesa")</script>'];
        foreach ($nomes as $indice => $nome) {
            $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), $nome);
            $plataforma->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
            $repositorio->conceder($mesa->id, $nome, [['pessoa' => $ana->identificadorNoDominio(), 'tipo' => 'vitoria']],
                new DateTimeImmutable($indice === 0 ? '2026-10-01T12:00:00Z' : '2026-10-04T12:00:00Z'));
        }
        $resposta = $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertSeeInOrder(array_reverse($nomes))->assertDontSee($nomes[2], false);
        $this->assertSame(array_reverse($nomes), array_column($resposta->viewData('trofeus'), 'mesa'));
    }

    #[Test]
    public function rotasDeConsultaContinuamProtegidas(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        foreach ([route('perfil.mostrar'), route('perfil.amigo', $ana->id), route('mesas.mostrar', $mesa->id)] as $rota) {
            $this->get($rota)->assertRedirect(route('login'));
        }
        $ana->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($ana)->get(route('perfil.mostrar'))->assertRedirect(route('verification.notice'));
        $estranho = User::factory()->create();
        $this->actingAs($estranho)->get(route('mesas.mostrar', $mesa->id))->assertNotFound();
    }

    private function prepararMesa(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        app(Plataforma::class)->naMesa($mesa->id, static fn (Mesa $mesa) => $mesa->adicionarMembro($bruno->identificadorNoDominio()));

        return [$mesa, $ana, $bruno];
    }

    private function abrirPartida(Mesa $mesa): void
    {
        app(Plataforma::class)->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', null, null, 1), $mesa->admin());
        app(Plataforma::class)->iniciarPartida($mesa->id, $mesa->admin());
    }

    private function vencer(Mesa $mesa, string $vencedor): void
    {
        $this->abrirPartida($mesa);
        app(Plataforma::class)->naMesa($mesa->id, static function (Mesa $mesa) use ($vencedor): void {
            $partida = $mesa->partidaAtiva();
            $tempos = [];
            foreach ($mesa->membrosAtivos() as $indice => $pessoa) {
                $tempos[$pessoa] = $pessoa === $vencedor ? 5000 : 10000 + $indice * 1000;
            }
            foreach ($mesa->membrosAtivos() as $pessoa) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });
    }

    private function trofeus(Mesa $mesa): array
    {
        return DB::table('trofeus')->where('mesa_id', $mesa->id)->orderBy('id')->get()->all();
    }
}
