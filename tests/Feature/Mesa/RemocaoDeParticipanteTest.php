<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Livewire\Mesa\MostrarMesa;
use App\Mesa\ClassificacaoDaMesa;
use App\Mesa\RankingDaMesa;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RemocaoDeParticipanteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
    }

    #[Test]
    public function admin_remove_participante_convidado(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComAnaEBruno();
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('removerDaMesa', $bruno->identificadorNoDominio())
            ->assertHasNoErrors();

        $mesa = app(Plataforma::class)->mesa($mesa->id);
        $this->assertFalse($mesa->membroAtivo($bruno->identificadorNoDominio()));
        $this->assertTrue($mesa->membroAtivo($ana->identificadorNoDominio()));
    }

    #[Test]
    public function admin_remove_participante_local(): void
    {
        [$ana, , $mesa] = $this->mesaComAnaEBruno();
        $plataforma = app(Plataforma::class);
        $admin = $ana->identificadorNoDominio();
        $local = null;
        $plataforma->naMesa($mesa->id, static function (Mesa $m) use ($admin, &$local): void {
            $local = $m->cadastrarParticipanteLocal('Zeca', 'zeca@example.test', $admin);
        });
        $this->actingAs($ana);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('removerDaMesa', $local)
            ->assertHasNoErrors();

        $this->assertFalse(app(Plataforma::class)->mesa($mesa->id)->membroAtivo($local));
    }

    #[Test]
    public function remover_preserva_pontos_no_ranking_mas_sai_da_classificacao(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComAnaEBruno();
        $plataforma = app(Plataforma::class);
        $admin = $ana->identificadorNoDominio();
        $brunoId = $bruno->identificadorNoDominio();
        $plataforma->naMesa($mesa->id, static function (Mesa $m) use ($admin, $brunoId): void {
            $partida = $m->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, 1), $admin);
            $partida->iniciar($admin);
            foreach ([$admin => 9000, $brunoId => 15000] as $participante => $tempo) {
                $partida->iniciarTentativa((string) $participante);
                $partida->concluirTentativa((string) $participante, $tempo);
            }
        });

        $plataforma->removerDaMesa($mesa->id, $brunoId, $admin);

        $mesa = $plataforma->mesa($mesa->id);
        $ranking = RankingDaMesa::de($mesa);
        $classificacao = ClassificacaoDaMesa::de($mesa);

        $this->assertContains($brunoId, array_column($ranking->linhas, 'id'), 'Pontos de partida concluída permanecem no ranking.');
        $this->assertNotContains($brunoId, array_column($classificacao->linhas, 'id'), 'Removido sai da classificação de membros ativos.');
    }

    #[Test]
    public function nao_remove_o_proprio_admin(): void
    {
        [$ana, , $mesa] = $this->mesaComAnaEBruno();
        $admin = $ana->identificadorNoDominio();

        $this->expectException(RegraViolada::class);
        app(Plataforma::class)->removerDaMesa($mesa->id, $admin, $admin);
    }

    #[Test]
    public function participante_comum_nao_remove_pelo_backend(): void
    {
        [$ana, $bruno, $mesa] = $this->mesaComAnaEBruno();

        try {
            app(Plataforma::class)->removerDaMesa(
                $mesa->id,
                $ana->identificadorNoDominio(),
                $bruno->identificadorNoDominio(),
            );
            $this->fail('Participante comum não deveria remover ninguém.');
        } catch (RegraViolada) {
            // esperado
        }

        $this->assertTrue(app(Plataforma::class)->mesa($mesa->id)->membroAtivo($ana->identificadorNoDominio()));
    }

    #[Test]
    public function admin_ve_cadeira_removivel_do_participante_e_o_modal(): void
    {
        [$ana, , $mesa] = $this->mesaComAnaEBruno();
        $this->actingAs($ana);

        $html = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertSee('data-test="modal-remocao"', false)
            ->assertSee('Remover da mesa?')
            ->assertSee('Manter na mesa')
            ->assertSee('Remover participante')
            ->html();

        // Uma cadeira removível (Bruno), nunca a do próprio admin.
        $this->assertSame(1, $this->xpath($html)->query('//*[@data-test="cadeira-removivel"]')->length);
    }

    #[Test]
    public function participante_comum_nao_ve_cadeira_removivel_nem_modal(): void
    {
        [, $bruno, $mesa] = $this->mesaComAnaEBruno();
        $this->actingAs($bruno);

        $html = Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->assertDontSee('data-test="modal-remocao"', false)
            ->html();

        $this->assertSame(0, $this->xpath($html)->query('//*[@data-test="cadeira-removivel"]')->length);
    }

    /** @return array{User, User, Mesa} */
    private function mesaComAnaEBruno(): array
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
