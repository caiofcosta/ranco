<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // O cadastro do RANÇO não entra na conta: quem entra é o link de confirmação enviado por e-mail.
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('cadastro.confira-seu-email'));

        $this->assertGuest();
        $this->assertTrue(User::where('email', 'test@example.com')->exists());
    }
}
