<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Dominio\Plataforma\Plataforma;
use App\Filament\Pages\DisponibilidadeDosJogos;
use App\Livewire\Mesa\MostrarMesa;
use App\Models\Jogo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DisponibilidadeDosJogosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function administrador_alterna_jogos_no_painel(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']));
        Filament::setCurrentPanel('painel');

        $this->get('/painel/jogos/disponibilidade')
            ->assertOk()
            ->assertSee('Labirinto')
            ->assertSee('Inativar');

        Livewire::test(DisponibilidadeDosJogos::class)
            ->assertSee('Labirinto')
            ->assertSee('Inativar')
            ->call('alternar', 'labirinto')
            ->assertSee('Ativar');

        $this->assertDatabaseHas('jogos', ['id' => 'labirinto', 'ativo' => false]);
    }

    #[Test]
    public function jogo_inativo_sai_das_listas_e_mostra_ops_em_acessos_diretos(): void
    {
        Jogo::query()->whereKey('labirinto')->update(['ativo' => false]);
        $this->actingAs(User::factory()->create());

        $this->get('/jogar')
            ->assertOk()
            ->assertDontSee('Labirinto');

        $this->get('/jogar/labirinto')
            ->assertNotFound()
            ->assertSee('OPS!')
            ->assertSee('Esse jogo não está disponível.');

        $this->post('/jogar/labirinto/iniciar')->assertNotFound()->assertSee('OPS!');
        $this->get('/jogar/nao-existe')->assertNotFound()->assertSee('OPS!');
    }

    #[Test]
    public function escolha_de_jogo_na_mesa_rejeita_criacao_de_jogo_indisponivel(): void
    {
        $usuario = User::factory()->create();
        $mesa = app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio(), 'Mesa teste');
        $this->actingAs($usuario);

        Livewire::test(MostrarMesa::class, ['id' => $mesa->id])
            ->call('abrirEscolhaDoJogo')
            ->assertSee('Labirinto');

        $this->postJson(route('mesas.partidas.criar', $mesa->id), ['jogo' => 'inexistente'])
            ->assertUnprocessable();

        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva());
    }

    #[Test]
    public function criacao_sem_jogo_nao_usa_labirinto_quando_ele_esta_inativo(): void
    {
        $usuario = User::factory()->create();
        $mesa = app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio(), 'Mesa teste');
        Jogo::query()->whereKey('labirinto')->update(['ativo' => false]);

        $this->actingAs($usuario)
            ->postJson(route('mesas.partidas.criar', $mesa->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jogo');

        $this->assertNull(app(Plataforma::class)->mesa($mesa->id)->partidaAtiva());
    }
}
