<?php

declare(strict_types=1);

namespace Tests\Feature\Conquista;

use App\Dominio\Conquista\ConcessorDeTrofeus;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Plataforma\RepositorioDeMesas;
use App\Dominio\Relogio;
use App\Mesa\NomeDaMesa;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class DatasDasConquistasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function concessaoGravaADataDoDominioEATelaMostraElaEmBrasilia(): void
    {
        $this->app->instance(Relogio::class, new RelogioFalso(new DateTimeImmutable('2026-01-15T15:00:00.000Z')));
        $this->app->forgetInstance(Plataforma::class);
        $this->app->forgetInstance(RepositorioDeMesas::class);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo da data');
        $plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $bruno): void {
            $mesa->adicionarMembro($bruno->identificadorNoDominio());
            $partida = $mesa->novaPartida(
                CatalogoDeJogos::configuracao('labirinto', null, null, 1),
                $ana->identificadorNoDominio(),
            );
            $partida->iniciar($ana->identificadorNoDominio());
            $tempos = [
                $ana->identificadorNoDominio() => 5000,
                $bruno->identificadorNoDominio() => 8000,
            ];
            while ($partida->situacao()->value === 'em_andamento' && $partida->vezAtual() !== null) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });

        $medalha = DB::table('medalhas')->where('mesa_id', $mesa->id)->where('colocacao', 1)->first();
        $this->assertSame('2026-01-15 15:00:00.000', $medalha->partida_criada_em);
        $this->assertNotSame('2026-01-15 15:00:00.000', $medalha->concedida_em);

        $this->app->instance(Relogio::class, new RelogioFalso(new DateTimeImmutable('2026-10-05T15:00:00.000Z')));
        $this->app->forgetInstance(ConcessorDeTrofeus::class);
        $this->app->forgetInstance(Plataforma::class);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $trofeu = DB::table('trofeus')->where('mesa_id', $mesa->id)->first();
        $this->assertSame('2026-01-15 15:00:00.000', $trofeu->mesa_criada_em);
        $this->assertSame('2026-10-05 15:00:00.000', $trofeu->concedido_em);

        $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertSee('15/01/2026')
            ->assertSee('2026-01-15T12:00:00-03:00', false)
            ->assertDontSee('05/10/2026');
        $this->get(route('mesas.mostrar', $mesa->id))->assertOk()
            ->assertSee('15/01/2026')->assertDontSee('05/10/2026');
    }

    #[Test]
    public function semDataDeCriacaoATelaUsaAConcessaoEAOrdemSegueADataMostrada(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $plataforma = app(Plataforma::class);
        $antiga = $this->mesaEncerrada($plataforma, $ana, 'Fevereiro antiga');
        $marco = $this->mesaEncerrada($plataforma, $ana, 'Marco');
        $recente = $this->mesaEncerrada($plataforma, $ana, 'Fevereiro recente');
        $this->gravarMedalha($antiga, '2026-08-01 12:00:00.000', '2026-02-01 15:00:00.000');
        $this->gravarMedalha($marco, '2026-03-01 15:00:00.000', null);
        $this->gravarMedalha($recente, '2026-01-01 12:00:00.000', '2026-02-01 15:00:00.000');

        $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertViewHas('medalhas', fn (array $medalhas): bool => array_column($medalhas, 'mesa') === [
                'Marco',
                'Fevereiro recente',
                'Fevereiro antiga',
            ] && $medalhas[0]['data'] === '01/03/2026'
                && $medalhas[1]['data'] === '01/02/2026'
                && $medalhas[2]['data'] === '01/02/2026');

        $mesaTrofeu = $this->mesaEncerrada($plataforma, $ana, 'Trofeu antigo');
        $mesaTrofeuNova = $this->mesaEncerrada($plataforma, $ana, 'Trofeu novo');
        DB::table('trofeus')->insert([
            [
                'pessoa' => $ana->identificadorNoDominio(),
                'mesa_id' => $mesaTrofeu->id,
                'tipo' => 'vitoria',
                'mesa_nome' => 'Trofeu antigo',
                'frase' => null,
                'concedido_em' => '2026-10-04 01:30:00.123',
                'mesa_criada_em' => null,
            ],
            [
                'pessoa' => $ana->identificadorNoDominio(),
                'mesa_id' => $mesaTrofeuNova->id,
                'tipo' => 'empate',
                'mesa_nome' => 'Trofeu novo',
                'frase' => null,
                'concedido_em' => '2026-01-01 12:00:00.000',
                'mesa_criada_em' => '2026-11-02 15:00:00.000',
            ],
        ]);
        $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertSee('03/10/2026')
            ->assertViewHas('trofeus', fn (array $trofeus): bool => array_column($trofeus, 'mesa') === [
                'Trofeu novo',
                'Trofeu antigo',
            ] && $trofeus[0]['data'] === '02/11/2026' && $trofeus[1]['data'] === '03/10/2026');
    }

    private function mesaEncerrada(Plataforma $plataforma, User $ana, string $nome): Mesa
    {
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), $nome);
        $plataforma->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        return $mesa;
    }

    private function gravarMedalha(Mesa $mesa, string $concessao, ?string $criacao): void
    {
        DB::table('medalhas')->insert([
            'pessoa' => $mesa->admin(),
            'mesa_id' => $mesa->id,
            'mesa_nome' => NomeDaMesa::de($mesa),
            'partida' => 1,
            'colocacao' => 1,
            'empate' => false,
            'jogo' => 'labirinto',
            'frase' => null,
            'concedida_em' => $concessao,
            'partida_criada_em' => $criacao,
        ]);
    }
}
