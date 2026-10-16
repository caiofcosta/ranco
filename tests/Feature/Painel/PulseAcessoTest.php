<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O dashboard do Laravel Pulse (/pulse) é liberado pelo gate `viewPulse`, que reaproveita a
 * mesma regra do Painel (App\Models\User::canAccessPanel): só os e-mails de RANCO_ADMINISTRADORES.
 * Aqui conferimos o gate direto, sem depender da rota (o ambiente de teste desliga o Pulse).
 */
final class PulseAcessoTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function usuarioComumNaoPodeVerOPulse(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $comum = User::factory()->create(['email' => 'qualquer@exemplo.com']);

        $this->assertFalse(Gate::forUser($comum)->allows('viewPulse'));
    }

    #[Test]
    public function administradorPodeVerOPulse(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $admin = User::factory()->create(['email' => 'admin@exemplo.com']);

        $this->assertTrue(Gate::forUser($admin)->allows('viewPulse'));
    }

    #[Test]
    public function visitanteNaoPodeVerOPulse(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);

        $this->assertFalse(Gate::allows('viewPulse'));
    }
}
