<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Dominio\Relogio;
use App\Filament\Widgets\EstatisticasGeraisWidget;
use App\Filament\Widgets\UltimosSeteDiasWidget;
use App\Models\User;
use DateTimeImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class DashboardDoPainelTest extends TestCase
{
    use RefreshDatabase;

    private function entrarComoAdministrador(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']));
        Filament::setCurrentPanel('painel');
    }

    #[Test]
    public function soAdministradorDoSistemaAbreAHomeDoPainel(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);

        $this->actingAs(User::factory()->create(['email' => 'qualquer@exemplo.com']))
            ->get('/painel')
            ->assertForbidden();
    }

    #[Test]
    public function aHomeTrazOsWidgetsDoRancoESemOsPadroesDoFilament(): void
    {
        $this->entrarComoAdministrador();

        $this->get('/painel')
            ->assertOk()
            ->assertSeeLivewire(EstatisticasGeraisWidget::class)
            ->assertSeeLivewire(UltimosSeteDiasWidget::class)
            ->assertDontSeeLivewire(AccountWidget::class)
            ->assertDontSeeLivewire(FilamentInfoWidget::class);
    }

    #[Test]
    public function asEstatisticasMostramOsSeteCardsComPollingDeSessentaSegundos(): void
    {
        $this->entrarComoAdministrador();

        Livewire::test(EstatisticasGeraisWidget::class)
            ->assertSeeInOrder([
                'Usuários cadastrados', 'Online agora', 'Mesas abertas', 'Mesas inativas',
                'Novos hoje', 'Criadas hoje', 'Finalizadas hoje',
            ])
            ->assertSee('Atividade nos últimos 2 minutos')
            ->assertSeeHtml('wire:poll.60s')
            ->assertDontSeeHtml('wire:poll.5s');
    }

    #[Test]
    public function oHistoricoMostraSeteDiasDoMaisAntigoParaHoje(): void
    {
        $this->app->instance(Relogio::class, new RelogioFalso(new DateTimeImmutable('2026-10-07T14:00:00+00:00')));
        $this->entrarComoAdministrador();

        Livewire::test(UltimosSeteDiasWidget::class)
            ->assertSee('Últimos 7 dias')
            ->assertSeeInOrder(['Dia', 'Novos usuários', 'Mesas criadas'])
            ->assertSeeInOrder(['01/10', '02/10', '03/10', '04/10', '05/10', '06/10', '07/10'])
            ->assertSeeHtml('wire:poll.60s');
    }
}
