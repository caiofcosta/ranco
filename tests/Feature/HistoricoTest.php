<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HistoricoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exigeLoginEEmailVerificado(): void
    {
        $this->get('/historico')->assertRedirect(route('login'));

        $usuario = User::factory()->unverified()->create();

        $this->actingAs($usuario)->get('/historico')
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function mostraEstadoVazioEMenuAtivo(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->actingAs($usuario)->get(route('historico'))
            ->assertOk()
            ->assertSee('Você ainda não participou de nenhuma mesa.')
            ->assertSee('data-test="menu-historico"', false)
            ->assertDontSee('data-test="mesa-no-historico"', false)
            ->assertDontSee('Páginas do histórico');

        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('historico'), '/').'"[^>]*aria-current="page"/s',
            $resposta->getContent(),
        );
    }

    #[Test]
    public function listaParticipacoesDiretasSemExporMesasDeOutraConta(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $this->mesa($ana, 'aberta', 'Mesa aberta', 'aberta');
        $this->membro('aberta', $ana);

        $this->mesa($bruno, 'encerrada', 'Mesa encerrada');
        $this->membro('encerrada', $ana);

        $this->mesa($bruno, 'abandonada', 'Mesa abandonada', 'aberta');
        $this->membro('abandonada', $ana, 'abandonada');

        $this->mesa($bruno, 'removida', 'Mesa removida', 'aberta');
        $this->membro('removida', $ana, 'removida');

        $this->mesa($bruno, 'privada', 'Mesa privada');
        $this->membro('privada', $bruno);

        $resposta = $this->actingAs($ana)->get(route('historico'))
            ->assertOk()
            ->assertSee('Mesa aberta')
            ->assertSee('Mesa encerrada')
            ->assertSee('Mesa abandonada')
            ->assertSee('Mesa removida')
            ->assertDontSee('Mesa privada')
            ->assertSee(route('mesas.mostrar', 'aberta'), false)
            ->assertSee(route('mesas.mostrar', 'encerrada'), false)
            ->assertDontSee(route('mesas.mostrar', 'abandonada'), false)
            ->assertDontSee(route('mesas.mostrar', 'removida'), false);

        $this->assertSame(4, $resposta->viewData('mesas')->total());
    }

    #[Test]
    public function incluiSomenteLocaisReconhecidosPelaContaSemDuplicarMesas(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        foreach (['reconhecida', 'pendente', 'rejeitada', 'outra', 'dupla'] as $id) {
            $this->mesa($bruno, $id, 'Local '.$id);
        }

        $this->local('reconhecida', $ana, 'reconhecida');
        $this->local('pendente', $ana, 'pendente');
        $this->local('rejeitada', $ana, 'rejeitada');
        $this->local('outra', $bruno, 'reconhecida');
        $this->local('dupla', $ana, 'reconhecida');
        $this->membro('dupla', $ana);

        $resposta = $this->actingAs($ana)->get(route('historico'))
            ->assertOk()
            ->assertSee('Local reconhecida')
            ->assertSee('Local dupla')
            ->assertSee('Participação local reconhecida')
            ->assertDontSee('Local pendente')
            ->assertDontSee('Local rejeitada')
            ->assertDontSee('Local outra')
            ->assertDontSee(route('mesas.mostrar', 'reconhecida'), false)
            ->assertSee(route('mesas.mostrar', 'dupla'), false);

        $this->assertSame(2, $resposta->viewData('mesas')->total());
        $this->assertSame(1, substr_count($resposta->getContent(), 'Local dupla'));
    }

    #[Test]
    public function paginaDezMesasComNumerosEOrdenacaoEstavel(): void
    {
        $ana = User::factory()->create();

        for ($numero = 1; $numero <= 23; $numero++) {
            $id = sprintf('mesa-%02d', $numero);
            $this->mesa($ana, $id, $id);
            $this->membro($id, $ana);
        }

        // A atualização prevalece sobre o identificador.
        DB::table('mesas')->where('id', 'mesa-01')
            ->update(['atualizada_em' => '2026-10-05 13:00:00']);

        $primeira = $this->actingAs($ana)->get(route('historico'))
            ->assertOk()
            ->assertSee('Página 1 de 3')
            ->assertSee('aria-label="Ir para a página 2"', false)
            ->assertSee('aria-label="Ir para a página 3"', false);

        $mesas = $primeira->viewData('mesas');
        $this->assertSame(23, $mesas->total());
        $this->assertSame(10, $mesas->perPage());
        $this->assertSame([
            'mesa-01', 'mesa-23', 'mesa-22', 'mesa-21', 'mesa-20',
            'mesa-19', 'mesa-18', 'mesa-17', 'mesa-16', 'mesa-15',
        ], array_column($mesas->items(), 'id'));

        $segunda = $this->get(route('historico', ['page' => 2]))
            ->assertOk()->assertSee('Página 2 de 3');

        $this->assertSame([
            'mesa-14', 'mesa-13', 'mesa-12', 'mesa-11', 'mesa-10',
            'mesa-09', 'mesa-08', 'mesa-07', 'mesa-06', 'mesa-05',
        ], array_column($segunda->viewData('mesas')->items(), 'id'));

        $terceira = $this->get(route('historico', ['page' => 3]))
            ->assertOk()->assertSee('Página 3 de 3');

        $this->assertSame([
            'mesa-04', 'mesa-03', 'mesa-02',
        ], array_column($terceira->viewData('mesas')->items(), 'id'));

        $this->get(route('historico', ['page' => 4]))
            ->assertOk()
            ->assertSee('Nenhuma mesa nesta página.')
            ->assertSee('Voltar à primeira página');
    }

    #[Test]
    public function usaNomeDoAdminQuandoAMesaNaoTemNome(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Lima']);
        $this->mesa($ana, 'sem-nome', '');
        $this->membro('sem-nome', $ana);

        $this->actingAs($ana)->get(route('historico'))
            ->assertOk()->assertSee('Mesa de Ana Lima');
    }

    #[Test]
    public function invisivelConsultaHistoricoMasNaoRecebeLinkDeMesaAberta(): void
    {
        $ana = User::factory()->create(['invisivel' => true]);

        $this->mesa($ana, 'aberta', 'Mesa aberta', 'aberta');
        $this->membro('aberta', $ana);
        $this->mesa($ana, 'encerrada', 'Mesa encerrada');
        $this->membro('encerrada', $ana, 'abandonada');

        $this->actingAs($ana)->get(route('historico'))
            ->assertOk()
            ->assertSee('Mesa aberta')
            ->assertSee('Mesa encerrada')
            ->assertDontSee(route('mesas.mostrar', 'aberta'), false)
            ->assertSee(route('mesas.mostrar', 'encerrada'), false);
    }

    #[Test]
    public function consultaApenasProjecoesSemSelecionarNemDesserializarEstado(): void
    {
        $ana = User::factory()->create();

        for ($numero = 1; $numero <= 12; $numero++) {
            $id = 'consulta-'.$numero;
            $this->mesa($ana, $id, $id);
            $this->membro($id, $ana);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            // Os estados das fixtures são inválidos: desserializá-los faria o GET falhar.
            $this->actingAs($ana)->get(route('historico'))->assertOk();

            $consultas = array_values(array_filter(
                DB::getQueryLog(),
                static fn (array $consulta): bool => str_contains($consulta['query'], 'from `mesas`'),
            ));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        // Uma contagem e uma busca limitada à página, sem consultas por mesa.
        $this->assertCount(2, $consultas);

        foreach ($consultas as $consulta) {
            $this->assertStringNotContainsString('estado', $consulta['query']);
            $this->assertDoesNotMatchRegularExpression(
                '/select\s+(?:`mesas`\.)?\*/i',
                $consulta['query'],
            );
        }

        $this->assertStringContainsString('limit 10', $consultas[1]['query']);
    }

    private function mesa(
        User $admin,
        string $id,
        string $nome,
        string $situacao = 'encerrada',
    ): void {
        DB::table('mesas')->insert([
            'id' => $id,
            'admin' => $admin->identificadorNoDominio(),
            'nome' => $nome,
            'situacao' => $situacao,
            'estado' => 'estado deliberadamente invalido',
            'versao' => 1,
            'atualizada_em' => '2026-10-05 12:00:00',
        ]);
    }

    private function membro(string $mesa, User $usuario, string $situacao = 'ativa'): void
    {
        DB::table('membros_mesa')->insert([
            'mesa_id' => $mesa,
            'usuario' => $usuario->identificadorNoDominio(),
            'situacao' => $situacao,
            'usuario_ativo' => null,
        ]);
    }

    private function local(string $mesa, User $usuario, string $situacao): void
    {
        $id = $mesa.':local:1';

        DB::table('participantes_locais')->insert([
            'id' => $id,
            'mesa_id' => $mesa,
            'nome' => $usuario->name,
            'email' => $usuario->email,
            'situacao' => $situacao,
            'usuario' => $situacao === 'reconhecida' ? $usuario->identificadorNoDominio() : null,
        ]);

        DB::table('membros_mesa')->insert([
            'mesa_id' => $mesa,
            'usuario' => $id,
            'situacao' => 'ativa',
            'usuario_ativo' => null,
        ]);
    }
}
