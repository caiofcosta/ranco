<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Filament\Resources\Opcoes\Pages\GerenciarOpcoes;
use App\Models\Opcao;
use App\Models\User;
use App\Opcoes\Lista;
use Database\Seeders\OpcoesSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OpcoesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function aMigracaoJaTrazAsSugestoesDeNomeDeMesa(): void
    {
        $rotulos = Opcao::query()->ativasDa(Lista::NomesDeMesa)->pluck('rotulo');

        $this->assertContains('Primos do caos', $rotulos);
        $this->assertSame('Família em jogo', $rotulos->first());
        $this->assertTrue($rotulos->every(static fn (string $rotulo): bool => mb_strlen($rotulo) <= 40), 'Nome de mesa tem até 40 caracteres.');
    }

    #[Test]
    public function oSeedPodeRodarDeNovoSemDuplicarNemReativar(): void
    {
        $total = Opcao::count();
        Opcao::where('valor', 'primos-do-caos')->update(['ativo' => false, 'rotulo' => 'Primos editados']);

        (new OpcoesSeeder())->run();

        $this->assertSame($total, Opcao::count());
        $editada = Opcao::where('valor', 'primos-do-caos')->sole();
        $this->assertFalse($editada->ativo);
        $this->assertSame('Primos editados', $editada->rotulo);
    }

    #[Test]
    public function asAtivasVemNaOrdemESemAsDesativadas(): void
    {
        Opcao::query()->delete();
        Opcao::create(['lista' => Lista::NomesDeMesa, 'valor' => 'b', 'rotulo' => 'Segunda', 'ordem' => 2]);
        Opcao::create(['lista' => Lista::NomesDeMesa, 'valor' => 'a', 'rotulo' => 'Primeira', 'ordem' => 1]);
        Opcao::create(['lista' => Lista::NomesDeMesa, 'valor' => 'c', 'rotulo' => 'Desativada', 'ordem' => 0, 'ativo' => false]);

        $this->assertSame(['Primeira', 'Segunda'], Opcao::query()->ativasDa(Lista::NomesDeMesa)->pluck('rotulo')->all());
    }

    #[Test]
    public function oValorNaoSeRepeteNaMesmaLista(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        Opcao::create(['lista' => Lista::NomesDeMesa, 'valor' => 'primos-do-caos', 'rotulo' => 'Outro rótulo']);
    }

    #[Test]
    public function soAdministradorDoSistemaAbreAsOpcoesNoPainel(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']))
            ->get('/painel/opcoes')
            ->assertOk()
            ->assertSee('Primos do caos');

        $this->actingAs(User::factory()->create(['email' => 'qualquer@exemplo.com']))
            ->get('/painel/opcoes')
            ->assertForbidden();
    }

    #[Test]
    public function oAdministradorCadastraUmaOpcaoPeloPainel(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']));
        Filament::setCurrentPanel('painel');

        Livewire::test(GerenciarOpcoes::class)
            ->callAction('create', data: [
                'lista' => Lista::NomesDeMesa->value,
                'rotulo' => 'Vizinhança unida',
                'valor' => 'vizinhanca-unida',
                'ordem' => 50,
                'ativo' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(Opcao::where('valor', 'vizinhanca-unida')->where('rotulo', 'Vizinhança unida')->exists());
    }

    #[Test]
    public function oAdministradorDuplicaUmaOpcaoComOutroValor(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']));
        Filament::setCurrentPanel('painel');
        $original = Opcao::where('valor', 'primos-do-caos')->sole();

        Livewire::test(GerenciarOpcoes::class)
            ->callTableAction('replicate', $original, data: ['rotulo' => 'Primos do caos 2', 'valor' => 'primos-do-caos-2'])
            ->assertHasNoTableActionErrors();

        $copia = Opcao::where('valor', 'primos-do-caos-2')->sole();
        $this->assertSame('Primos do caos 2', $copia->rotulo);
        $this->assertSame(Lista::NomesDeMesa, $copia->lista);
    }
}
