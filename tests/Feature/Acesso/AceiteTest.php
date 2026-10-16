<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Acesso\Aceites;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AceiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['testes.aceite_automatico' => false]);
    }

    #[Test]
    public function sem_aceite_nao_entra_nas_paginas_protegidas(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/dashboard', '/mesas/inexistente', '/jogar', '/jogar/labirinto', '/amigos', '/perfil', '/settings/profile'] as $url) {
            $this->get($url)->assertRedirect(route('aceite.mostrar'));
        }
        $this->getJson(route('dashboard'))->assertForbidden()->assertJsonStructure(['mensagem'])->assertHeaderMissing('Location');
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-mesa.1', 'socket_id' => '1.1'])->assertForbidden()->assertJsonStructure(['mensagem']);
        $this->withHeader('X-Livewire', 'true')->get(route('dashboard'))->assertForbidden();
    }

    #[Test]
    public function aceite_grava_versao_data_e_ip_uma_unica_vez_e_volta_ao_destino(): void
    {
        $pessoa = User::factory()->create();
        $this->travelTo(now('UTC')->startOfSecond());
        $agora = now('UTC')->format('Y-m-d H:i:s');
        $this->actingAs($pessoa)->get('/amigos')->assertRedirect(route('aceite.mostrar'));
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::1'])->post('/aceite', [
            'confirmacao' => 'on', 'versao' => Aceites::VERSAO_VIGENTE,
        ])->assertRedirect(url('/amigos'));
        $this->assertDatabaseHas('aceites', ['user_id' => $pessoa->id, 'versao' => Aceites::VERSAO_VIGENTE, 'ip' => '2001:db8::1', 'aceito_em' => $agora]);
        $this->travel(1)->hours();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->post('/aceite', ['confirmacao' => 'on', 'versao' => Aceites::VERSAO_VIGENTE])->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('aceites', 1);
        $this->assertDatabaseHas('aceites', ['ip' => '2001:db8::1', 'aceito_em' => $agora]);
        $this->get(route('dashboard'))->assertOk();
    }

    #[Test]
    public function exige_checkbox_e_versao_atual(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/aceite', ['versao' => Aceites::VERSAO_VIGENTE])->assertSessionHasErrors(['confirmacao' => 'Para continuar, marque a confirmação.']);
        $this->post('/aceite', ['confirmacao' => 'on', 'versao' => 'antiga'])->assertSessionHasErrors('versao');
        $this->assertDatabaseCount('aceites', 0);
    }

    #[Test]
    public function versao_anterior_nao_libera_o_uso(): void
    {
        $pessoa = User::factory()->create();
        $pessoa->aceites()->create(['versao' => 'antiga', 'aceito_em' => now('UTC'), 'ip' => '192.0.2.1']);
        $this->actingAs($pessoa)->get(route('dashboard'))->assertRedirect(route('aceite.mostrar'));
        $this->get('/aceite')->assertOk()->assertSee('As regras mudaram. Leia e aceite a nova versão para continuar.');
        $this->post('/aceite', ['confirmacao' => 'on', 'versao' => Aceites::VERSAO_VIGENTE])->assertRedirect();
        $this->assertDatabaseCount('aceites', 2);
    }

    #[Test]
    public function documentos_sao_publicos_e_logout_continua_disponivel(): void
    {
        foreach (['/termos', '/privacidade', '/conduta'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs(User::factory()->create());
        foreach (['/termos', '/privacidade', '/conduta', '/conta/excluir'] as $url) {
            $this->get($url)->assertOk();
        }
        $tela = $this->get('/aceite')->assertOk()->assertSee('Antes de entrar')->assertSee('18 anos ou mais.')->assertSee('Aceitar e entrar');
        $this->assertSame(1, substr_count($tela->getContent(), 'type="checkbox"'));
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    #[Test]
    public function livewire_nao_permite_alterar_perfil_com_aceite_vencido(): void
    {
        $pessoa = User::factory()->aceitou()->create(['name' => 'Antes']);
        $pagina = $this->actingAs($pessoa)->get('/settings/profile')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $pagina->getContent(), $encontrados);
        $snapshot = null;
        foreach ($encontrados[1] as $codificado) {
            $json = html_entity_decode($codificado, ENT_QUOTES, 'UTF-8');
            if (str_contains(json_decode($json, true)['memo']['name'], 'profile')) {
                $snapshot = $json;
                break;
            }
        }
        $this->assertNotNull($snapshot);
        $pessoa->aceites()->update(['versao' => 'antiga']);
        $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => ['name' => 'Depois'],
            'calls' => [['path' => '', 'method' => 'updateProfileInformation', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertForbidden()->assertJsonStructure(['mensagem']);
        $this->assertSame('Antes', $pessoa->fresh()->name);
    }

    #[Test]
    public function exclusao_pelo_livewire_funciona_sem_aceite_com_e_sem_senha(): void
    {
        foreach ([true, false] as $temSenha) {
            $pessoa = User::factory()->unverified()->create($temSenha ? [] : ['password' => null]);
            $pagina = $this->actingAs($pessoa)->get('/conta/excluir')->assertOk();
            $snapshot = $this->snapshot($pagina->getContent(), 'delete-user-modal');
            $this->postJson(Livewire::getUpdateUri(), ['components' => [[
                'snapshot' => $snapshot,
                'updates' => $temSenha ? ['password' => 'password'] : ['confirmacao' => $pessoa->email],
                'calls' => [['path' => '', 'method' => 'deleteUser', 'params' => []]],
            ]]], ['X-Livewire' => 'true'])->assertOk();
            $this->assertNull($pessoa->fresh());
            $this->assertGuest();
        }
    }

    #[Test]
    public function painel_mantem_autorizacao_propria_sem_exigir_aceite(): void
    {
        $pessoa = User::factory()->create();
        config(['ranco.administradores' => [$pessoa->email]]);
        $pagina = $this->actingAs($pessoa)->get('/painel')->assertOk();
        $snapshot = $this->snapshot($pagina->getContent(), '');
        $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertOk();
        $this->assertDatabaseCount('aceites', 0);
        $this->postJson(route('painel.labirinto.previa'), [])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->get('/painel')->assertForbidden();
        $this->postJson(route('painel.labirinto.previa'), [])->assertForbidden();
    }

    private function snapshot(string $html, string $nome): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $encontrados);
        foreach ($encontrados[1] as $codificado) {
            $json = html_entity_decode($codificado, ENT_QUOTES, 'UTF-8');
            if (str_contains(json_decode($json, true)['memo']['name'], $nome)) {
                return $json;
            }
        }
        $this->fail('Componente não encontrado: '.$nome);
    }

    #[Test]
    public function excluir_conta_remove_aceites(): void
    {
        $pessoa = User::factory()->aceitou()->create();
        $pessoa->delete();
        $this->assertDatabaseCount('aceites', 0);
    }
}
