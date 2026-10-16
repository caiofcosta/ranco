<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Filament\Resources\ModalDeNotificacaoResource\Pages\GerenciarModaisDeNotificacao;
use App\Models\ModalDeNotificacao;
use App\Models\User;
use Database\Seeders\ModalDeOnboardingSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModaisDeNotificacaoTest extends TestCase
{
    use RefreshDatabase;

    private function entrarComoAdministrador(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']));
        Filament::setCurrentPanel('painel');
    }

    #[Test]
    public function oAdministradorAbreAListaDeModais(): void
    {
        (new ModalDeOnboardingSeeder())->run();
        $this->entrarComoAdministrador();

        $this->get('/painel/modais-de-notificacao')
            ->assertOk()
            ->assertSee('Primeira vez por aqui?');
    }

    #[Test]
    public function editarPeloPainelPreservaAsSecoes(): void
    {
        (new ModalDeOnboardingSeeder())->run();
        // Há outros modais criados por migration (orientação da mesa por papel); edita o de onboarding.
        $modal = ModalDeNotificacao::where('tipo', 'onboarding')->sole();
        $this->entrarComoAdministrador();

        Livewire::test(GerenciarModaisDeNotificacao::class)
            ->callTableAction('edit', $modal, data: ['titulo' => 'Primeira vez aqui?'])
            ->assertHasNoTableActionErrors();

        $modal->refresh();
        $this->assertSame('Primeira vez aqui?', $modal->titulo);
        $this->assertSame(['Mesas', 'Agora', 'Jogando'], array_column($modal->conteudo['secoes'], 'titulo'));
    }
}
