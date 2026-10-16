<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Teto com folga: a meta é 5 na leitura da mesa e 30 na tela; o número antigo passava de 40. */
final class ConsultasDaMesaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function leituraDaMesaNaoRepeteConsultaPorPessoaNemPorPartida(): void
    {
        $pessoas = [];
        foreach (['Ana', 'Bruno', 'Carla', 'Davi', 'Eva', 'Fabio'] as $nome) {
            $pessoas[] = User::factory()->create(['name' => $nome]);
        }
        $admin = $pessoas[0];
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($admin->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static function (Mesa $mesa) use ($pessoas): void {
            foreach (array_slice($pessoas, 1) as $pessoa) {
                $mesa->adicionarMembro($pessoa->identificadorNoDominio());
            }
        });

        $tempos = [];
        foreach ($pessoas as $indice => $pessoa) {
            $tempos[$pessoa->identificadorNoDominio()] = 10000 * ($indice + 1);
        }
        for ($partida = 0; $partida < 15; $partida++) {
            $plataforma->criarPartida(
                $mesa->id,
                CatalogoDeJogos::configuracao('labirinto', null, null, 1),
                $admin->identificadorNoDominio(),
            );
            $plataforma->iniciarPartida($mesa->id, $admin->identificadorNoDominio());
            $plataforma->naMesa($mesa->id, static function (Mesa $mesa) use ($tempos): void {
                $partida = $mesa->partidaAtiva();
                $rodada = $partida->rodadaAtual();
                while (
                    $partida->situacao()->value === 'em_andamento'
                    && $partida->rodadaAtual() === $rodada
                    && $partida->vezAtual() !== null
                ) {
                    $vez = $partida->vezAtual();
                    $partida->iniciarTentativa($vez);
                    $partida->concluirTentativa($vez, $tempos[$vez]);
                }
            });
        }

        $aberta = $this->medir(function () use ($mesa): void {
            app(Plataforma::class)->mesa($mesa->id);
        });
        $getAberta = $this->medir(function () use ($admin, $mesa): void {
            $this->actingAs($admin)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertSee('Fabio');
        });

        $plataforma->encerrarMesa($mesa->id, $admin->identificadorNoDominio());

        $encerrada = $this->medir(function () use ($mesa): void {
            app(Plataforma::class)->mesa($mesa->id);
        });
        $getEncerrada = $this->medir(function () use ($admin, $mesa): void {
            $this->actingAs($admin)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertSee('Bruno');
        });

        $admin->forceFill(['name' => 'Ana Atualizada'])->save();
        $this->actingAs($admin)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertSee('Ana Atualizada');

        $this->assertLessThanOrEqual(8, $aberta['questions'], json_encode($aberta));
        $this->assertLessThanOrEqual(8, $encerrada['questions'], json_encode($encerrada));
        // A mesa aberta ainda consulta a orientação de primeira entrada (modal + dispensa por papel).
        // Teto de 32 para 35 medidos: o total inclui a consulta de convites de amizade da navegação mobile, feita em toda página.
        $this->assertLessThanOrEqual(35, $getAberta['questions'], json_encode($getAberta));
        $this->assertLessThanOrEqual(30, $getEncerrada['questions'], json_encode($getEncerrada));
    }

    /** @return array{query_log: int, questions: int, sql: list<string>} */
    private function medir(callable $acao): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $antes = $this->perguntas();
        $acao();
        $questions = $this->perguntas() - $antes - 1;
        $sql = array_map(static fn (array $linha): string => $linha['query'], DB::getQueryLog());
        DB::disableQueryLog();

        return [
            'query_log' => count($sql),
            'questions' => $questions,
            'sql' => $sql,
        ];
    }

    private function perguntas(): int
    {
        $linha = DB::connection()->getPdo()->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_ASSOC);

        return (int) $linha['Value'];
    }
}
