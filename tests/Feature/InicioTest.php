<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InicioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function visitanteEntraPelaHome(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Entre na sua conta.', false)
            ->assertSee(route('login.store'), false)
            ->assertSee(route('register'), false);
    }

    #[Test]
    public function quemJaEntrouNaoVeOFormulario(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Você já entrou.', false)
            ->assertSee(route('dashboard'), false)
            ->assertDontSee('name="password"', false);
    }
}
