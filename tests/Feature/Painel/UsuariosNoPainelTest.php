<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Filament\Pages\BuscarUsuario;
use App\Filament\Pages\UsuariosOnline;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UsuariosNoPainelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lista_contas_com_sessao_atualizada_nos_ultimos_cinco_minutos(): void
    {
        $this->administrador();
        $online = User::factory()->create(['name' => 'Pessoa Online', 'email' => 'online@exemplo.com']);
        $antiga = User::factory()->create(['name' => 'Pessoa Ausente', 'email' => 'ausente@exemplo.com']);
        $agora = now()->timestamp;

        DB::table('sessions')->insert([
            $this->sessao($online->id, $agora - 60),
            $this->sessao($antiga->id, $agora - (UsuariosOnline::JANELA_MINUTOS * 60) - 1),
        ]);

        $this->get('/painel/usuarios/online')
            ->assertOk()
            ->assertSee('Pessoa Online')
            ->assertSee('online@exemplo.com')
            ->assertDontSee('Pessoa Ausente')
            ->assertSee('Última atividade');
    }

    #[Test]
    public function busca_por_nome_ou_email_e_mostra_apenas_dados_cadastrais_selecionados(): void
    {
        $this->administrador();
        $usuario = User::factory()->create([
            'name' => 'Conta Invisível',
            'email' => 'conta@exemplo.com',
            'invisivel' => true,
        ]);

        Livewire::test(BuscarUsuario::class)
            ->assertSee('usuários cadastrados')
            ->assertSee('Conta Invisível')
            ->set('busca', 'conta@')
            ->assertSee('Conta Invisível')
            ->assertSee('conta@exemplo.com')
            ->call('selecionar', $usuario->id)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('Criada em')
            ->assertSee('E-mail confirmado')
            ->assertSee('Invisível para amigos')
            ->assertSee('Bloquear conta')
            ->assertSee('Excluir conta')
            ->assertSee('Sim')
            ->assertDontSee('password');
    }

    #[Test]
    public function lista_usuarios_em_paginas_e_reinicia_a_pagina_ao_filtrar(): void
    {
        $this->administrador();
        foreach (range(0, 25) as $numero) {
            User::factory()->create([
                'name' => sprintf('Usuário %02d', $numero),
                'email' => sprintf('usuario%02d@exemplo.com', $numero),
            ]);
        }

        Livewire::test(BuscarUsuario::class)
            ->assertSee('27 usuários cadastrados')
            ->assertSee('Usuário 00')
            ->assertDontSee('Usuário 25')
            ->call('nextPage')
            ->assertSee('Usuário 25')
            ->set('busca', 'Usuário 00')
            ->assertSee('1 usuário encontrado')
            ->assertSee('Usuário 00')
            ->assertDontSee('Usuário 25');
    }

    #[Test]
    public function modal_reune_dados_de_relacoes_jogos_conquistas_e_acessos_sem_expor_ip(): void
    {
        $this->administrador();
        $usuario = User::factory()->create(['name' => 'Conta Consultada', 'email' => 'consultada@exemplo.com']);
        $amigo = User::factory()->create(['name' => 'Amigo Consultado', 'email' => 'amigo@exemplo.com']);
        $par = [(string) $usuario->id, (string) $amigo->id];
        sort($par, SORT_STRING);
        DB::table('amizades')->insert([
            'usuario_a' => $par[0],
            'usuario_b' => $par[1],
            'criada_em' => now()->utc(),
        ]);
        $mesa = app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio(), 'Mesa consultada');
        DB::table('convites')->insert([
            'id' => fake()->uuid(),
            'mesa_id' => $mesa->id,
            'de' => $usuario->identificadorNoDominio(),
            'para' => $amigo->identificadorNoDominio(),
            'situacao' => 'pendente',
            'criado_em' => now()->utc(),
        ]);
        DB::table('convites_de_amizade')->insert([
            'de' => $usuario->identificadorNoDominio(),
            'para' => $amigo->id,
            'expira_em' => now()->addDay(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        DB::table('aceites')->insert([
            'user_id' => $usuario->id,
            'versao' => '2026-10',
            'aceito_em' => now()->utc(),
            'ip' => '192.0.2.10',
        ]);
        DB::table('medalhas')->insert([
            'pessoa' => $usuario->identificadorNoDominio(),
            'mesa_id' => $mesa->id,
            'partida' => 1,
            'colocacao' => 1,
            'empate' => false,
            'jogo' => 'labirinto',
            'mesa_nome' => 'Mesa consultada',
            'concedida_em' => now()->utc(),
        ]);
        DB::table('trofeus')->insert([
            'pessoa' => $usuario->identificadorNoDominio(),
            'mesa_id' => $mesa->id,
            'tipo' => 'vitoria',
            'mesa_nome' => 'Mesa consultada',
            'concedido_em' => now()->utc(),
        ]);
        DB::table('registros_de_acesso')->insert([
            'user_id' => $usuario->id,
            'ip' => '203.0.113.10',
            'porta_origem' => 12345,
            'ocorrido_em' => now()->utc(),
        ]);
        Livewire::test(BuscarUsuario::class)
            ->set('busca', 'consultada@')
            ->call('selecionar', $usuario->id)
            ->assertSee('Conta Consultada')
            ->assertSee('Amigo Consultado')
            ->assertSee('Convites de mesa')
            ->assertSee('Convites de amizade')
            ->assertSee('Aceites')
            ->assertSee('2026-10')
            ->assertSee('Mesa consultada')
            ->assertSee('Troféu de vitória')
            ->assertSee('Acessos recentes')
            ->assertDontSee('203.0.113.10')
            ->assertDontSee('192.0.2.10')
            ->assertDontSee('12345');
    }

    #[Test]
    public function bloquear_conta_encerra_sessoes_e_recusa_login_com_senha_correta(): void
    {
        $administrador = $this->administrador();
        $usuario = User::factory()->create([
            'email' => 'bloqueado@exemplo.com',
            'password' => Hash::make('senha-correta'),
        ]);
        DB::table('sessions')->insert($this->sessao($usuario->id, now()->timestamp));

        Livewire::test(BuscarUsuario::class)
            ->set('busca', 'bloqueado@')
            ->call('selecionar', $usuario->id)
            ->call('bloquearUsuario')
            ->assertSee('Bloqueado em');

        $this->assertDatabaseHas('users', ['id' => $usuario->id]);
        $this->assertNotNull($usuario->fresh()->bloqueado_em);
        $this->assertDatabaseMissing('sessions', ['user_id' => $usuario->id]);

        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $usuario->email, 'password' => 'senha-correta'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($administrador);
        Filament::setCurrentPanel('painel');
        Livewire::test(BuscarUsuario::class)
            ->set('busca', 'bloqueado@')
            ->call('selecionar', $usuario->id)
            ->call('desbloquearUsuario');
        $this->assertNull($usuario->fresh()->bloqueado_em);
    }

    #[Test]
    public function sessao_existente_de_conta_bloqueada_e_encerrada_no_proximo_acesso(): void
    {
        $usuario = User::factory()->create(['bloqueado_em' => now()]);

        $this->actingAs($usuario)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function excluir_conta_fecha_suas_mesas_apaga_dados_proprios_e_preserva_historico_compartilhado(): void
    {
        $this->administrador();
        $usuario = User::factory()->create(['email' => 'excluir@exemplo.com']);
        $amigo = User::factory()->create(['email' => 'amigo-excluir@exemplo.com']);
        $par = [(string) $usuario->id, (string) $amigo->id];
        sort($par, SORT_STRING);
        DB::table('amizades')->insert([
            'usuario_a' => $par[0],
            'usuario_b' => $par[1],
            'criada_em' => now()->utc(),
        ]);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($usuario->identificadorNoDominio(), 'Mesa a preservar');
        $plataforma->naMesa($mesa->id, static function (Mesa $mesa) use ($amigo): void {
            $mesa->adicionarMembro($amigo->identificadorNoDominio());
        });
        $mesaDoAmigo = $plataforma->criarMesa($amigo->identificadorNoDominio(), 'Mesa do amigo');
        $plataforma->naMesa($mesaDoAmigo->id, static function (Mesa $mesa) use ($usuario): void {
            $mesa->adicionarMembro($usuario->identificadorNoDominio());
        });
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
        $plataforma->criarPartida(
            $mesa->id,
            CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null),
            $usuario->identificadorNoDominio(),
        );
        $plataforma->iniciarPartida($mesa->id, $usuario->identificadorNoDominio());
        DB::table('registros_de_acesso')->insert([
            'user_id' => $usuario->id,
            'ip' => '203.0.113.20',
            'porta_origem' => 12345,
            'ocorrido_em' => now()->utc(),
        ]);
        DB::table('sessions')->insert($this->sessao($usuario->id, now()->timestamp));

        Livewire::test(BuscarUsuario::class)
            ->set('busca', 'excluir@')
            ->call('selecionar', $usuario->id)
            ->call('excluirUsuario')
            ->assertDontSee('Conta Consultada');

        $this->assertDatabaseMissing('users', ['id' => $usuario->id]);
        $this->assertDatabaseHas('mesas', [
            'id' => $mesa->id,
            'situacao' => 'encerrada',
            'partida_em_andamento' => false,
        ]);
        $this->assertDatabaseHas('mesas', ['id' => $mesaDoAmigo->id, 'situacao' => 'aberta']);
        $this->assertDatabaseHas('membros_mesa', [
            'mesa_id' => $mesaDoAmigo->id,
            'usuario' => (string) $usuario->id,
            'situacao' => 'abandonada',
        ]);
        $this->assertDatabaseMissing('medalhas', ['mesa_id' => $mesa->id]);
        $this->assertDatabaseHas('membros_mesa', ['mesa_id' => $mesa->id, 'usuario' => (string) $usuario->id]);
        $this->assertDatabaseMissing('amizades', ['usuario_a' => $par[0], 'usuario_b' => $par[1]]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $usuario->id]);
        $this->assertDatabaseHas('registros_de_acesso', ['user_id' => $usuario->id, 'ip' => '203.0.113.20']);
    }

    private function administrador(): User
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $administrador = User::factory()->create(['email' => 'admin@exemplo.com']);
        $this->actingAs($administrador);
        Filament::setCurrentPanel('painel');

        return $administrador;
    }

    /** @return array{id: string, user_id: int, ip_address: string, user_agent: string, payload: string, last_activity: int} */
    private function sessao(int $usuarioId, int $atividade): array
    {
        return [
            'id' => fake()->uuid(),
            'user_id' => $usuarioId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'teste',
            'payload' => '',
            'last_activity' => $atividade,
        ];
    }
}
