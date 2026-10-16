<?php

declare(strict_types=1);

namespace Tests\Feature\Conquista;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TelasDoTrofeuTest extends TestCase
{
    use RefreshDatabase;

    private const VITORIA = 'Levou a mesa. O resto da turma já está inventando desculpa.';

    private const EMPATE = 'Topo da mesa dividido com Bruno. Cada um vai contar essa história do seu jeito.';

    #[Test]
    public function perfilEMesaEncerradaMostramAFraseEOEmpateMostraONomeDaMesa(): void
    {
        DB::table('provocacoes')
            ->whereIn('texto', [
                'Primeiro lugar da mesa inteira. Alguém precisava, e foi você.',
                'A mesa acabou, e o topo é seu. Vai ser difícil ser modesto com isso.',
                'Empate no primeiro lugar com {outro}. Ninguém ganhou sozinho, e ninguém vai deixar isso quieto.',
                'Você e {outro} ficaram juntos no topo da mesa. Sorte de um, habilidade do outro, e vice-versa.',
            ])->update(['ativa' => false]);

        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $vitoria = $this->mesaCom($ana, $bruno, 'Mesa da vitoria');
        $this->jogar($vitoria, [$ana->identificadorNoDominio() => 5000, $bruno->identificadorNoDominio() => 8000]);
        $plataforma->encerrarMesa($vitoria->id, $ana->identificadorNoDominio());
        $empate = $this->mesaCom($ana, $bruno, 'Mesa do empate');
        $this->jogar($empate, [$ana->identificadorNoDominio() => 5000, $bruno->identificadorNoDominio() => 5000]);
        $plataforma->encerrarMesa($empate->id, $ana->identificadorNoDominio());

        $perfil = $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertSee(self::VITORIA, false)
            ->assertSee(self::EMPATE, false)
            ->assertSee('data-test="mesa-do-trofeu-empatado"', false)
            ->assertSee('Mesa do empate', false);
        $this->assertSame(1, substr_count($perfil->getContent(), 'data-test="mesa-do-trofeu-empatado"'));

        $this->get(route('mesas.mostrar', $vitoria->id))->assertOk()
            ->assertSee('data-test="frase-do-trofeu"', false)
            ->assertSee(self::VITORIA, false)
            ->assertDontSee('data-test="mesa-do-trofeu-empatado"', false);
        $this->get(route('mesas.mostrar', $empate->id))->assertOk()
            ->assertSee(self::EMPATE, false);
    }

    /** @param array<string, int> $tempos */
    private function jogar(Mesa $mesa, array $tempos): void
    {
        $admin = $mesa->admin();
        app(Plataforma::class)->naMesa($mesa->id, function (Mesa $mesa) use ($admin, $tempos): void {
            $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, 1), $admin);
            $partida->iniciar($admin);
            while ($partida->situacao()->value === 'em_andamento' && $partida->vezAtual() !== null) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });
    }

    private function mesaCom(User $admin, User $outro, string $nome): Mesa
    {
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($admin->identificadorNoDominio(), $nome);
        $plataforma->naMesa($mesa->id, static function (Mesa $mesa) use ($outro): void {
            $mesa->adicionarMembro($outro->identificadorNoDominio());
        });

        return $plataforma->mesa($mesa->id);
    }
}
