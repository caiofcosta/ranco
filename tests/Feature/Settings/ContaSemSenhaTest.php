<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContaSemSenhaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function contaSemSenhaAbreSegurancaSemConfirmarSenha(): void
    {
        $usuario = User::factory()->semSenha()->create();

        $this->actingAs($usuario)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee(__('Create password'))
            ->assertDontSee(__('Current password'));
    }

    #[Test]
    public function contaSemSenhaCriaAPrimeiraSenhaSemInformarAAtual(): void
    {
        $usuario = User::factory()->semSenha()->create();

        $this->actingAs($usuario);

        Livewire::test('pages::settings.security')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $usuario->refresh();

        $this->assertTrue($usuario->temSenha());
        $this->assertTrue(Hash::check('new-password', $usuario->password));
    }

    #[Test]
    public function depoisDeCriarSenhaATrocaPedeAAtual(): void
    {
        $usuario = User::factory()->semSenha()->create();

        $this->actingAs($usuario);

        Livewire::test('pages::settings.security')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->actingAs($usuario->fresh())
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));

        Livewire::test('pages::settings.security')
            ->set('password', 'outra-senha-12')
            ->set('password_confirmation', 'outra-senha-12')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
    }

    #[Test]
    public function contaSemSenhaExcluiAContaConfirmandoOEmail(): void
    {
        $usuario = User::factory()->semSenha()->create();

        $this->actingAs($usuario);

        Livewire::test('pages::settings.delete-user-modal')
            ->set('confirmacao', $usuario->email)
            ->call('deleteUser')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($usuario->fresh());
        $this->assertFalse(auth()->check());
    }

    #[Test]
    public function contaSemSenhaNaoExcluiComEmailErrado(): void
    {
        $usuario = User::factory()->semSenha()->create();

        $this->actingAs($usuario);

        Livewire::test('pages::settings.delete-user-modal')
            ->set('confirmacao', 'outro@exemplo.com')
            ->call('deleteUser')
            ->assertHasErrors(['confirmacao']);

        $this->assertNotNull($usuario->fresh());
    }
}
