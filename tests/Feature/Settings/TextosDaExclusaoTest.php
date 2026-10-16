<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TextosDaExclusaoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function formulario_e_modal_explicam_retencao_com_e_sem_senha_nos_dois_acessos(): void
    {
        foreach ([true, false] as $temSenha) {
            $pessoa = User::factory()->create($temSenha ? [] : ['password' => null]);
            $this->actingAs($pessoa);
            foreach (['/settings/profile', '/conta/excluir'] as $url) {
                $this->get($url)->assertOk()
                    ->assertSee('Apaga sua conta e seus dados, com as exceções da Política de Privacidade.')
                    ->assertSee('Ao excluir sua conta, apagamos seus dados de conta e seus aceites. Os registros de acesso (IP, data e hora) ficam guardados por 6 meses, e as mesas e partidas de que você participou continuam no histórico dos outros participantes, inclusive com seu nome. Detalhes na')
                    ->assertSee('href="'.route('documentos.privacidade').'"', false)
                    ->assertSee($temSenha ? 'Digite sua senha para confirmar.' : 'Digite seu e-mail para confirmar.')
                    ->assertDontSee($temSenha ? 'Digite seu e-mail para confirmar.' : 'Digite sua senha para confirmar.')
                    ->assertDontSee(__('Delete your account and all of its resources'));
            }
        }
    }
}
