<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Acesso\RegistrosDeAcesso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ContaGoogle;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrosDeAcessoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function senha_google_e_link_registram_entradas_com_ip(): void
    {
        $pessoa = User::factory()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post('/login', ['email' => $pessoa->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($pessoa);
        $this->assertDatabaseHas('registros_de_acesso', ['user_id' => $pessoa->id, 'ip' => '192.0.2.10']);
        Auth::logout();
        $link = URL::temporarySignedRoute('acesso-por-link.entrar', now()->addMinutes(30), ['usuario' => $pessoa->id, 'codigo' => str_repeat('a', 40)]);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::10'])->get($link)->assertRedirect();
        $this->assertDatabaseHas('registros_de_acesso', ['user_id' => $pessoa->id, 'ip' => '2001:db8::10']);
        Auth::logout();
        config(['services.google.client_id' => 'teste', 'services.google.client_secret' => 'teste']);
        $conta = (new ContaGoogle)->setRaw(['email_verified' => true])->map(['id' => 'google-teste', 'name' => 'Pessoa', 'email' => $pessoa->email]);
        $google = Mockery::mock(Provider::class);
        $google->shouldReceive('user')->once()->andReturn($conta);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($google);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])->get(route('google.retorno'))->assertRedirect();
        $this->assertDatabaseHas('registros_de_acesso', ['user_id' => $pessoa->id, 'ip' => '192.0.2.11']);
        $this->assertDatabaseCount('registros_de_acesso', 3);
    }

    #[Test]
    public function lembrar_de_mim_gera_registro_na_retomada(): void
    {
        $pessoa = User::factory()->create(['remember_token' => str_repeat('r', 60)]);
        $cookie = Auth::guard()->getRecallerName();
        $this->withCookie($cookie, $pessoa->id.'|'.$pessoa->remember_token.'|'.$pessoa->password)
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($pessoa);
        $this->assertTrue(Auth::guard()->viaRemember());
        $this->assertDatabaseCount('registros_de_acesso', 1);
        $this->assertDatabaseHas('registros_de_acesso', ['user_id' => $pessoa->id, 'ip' => '192.0.2.20']);
    }

    #[Test]
    public function uso_deduplica_mesmo_sem_cache_por_uma_hora_e_mudanca_de_ip_registra(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:15:00', 'UTC'));
        $pessoa = User::factory()->create();
        $this->actingAs($pessoa)->withServerVariables(['REMOTE_ADDR' => '192.0.2.30'])->get('/dashboard')->assertOk();
        $this->get('/amigos')->assertOk();
        Cache::flush();
        $this->travel(59)->minutes();
        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseCount('registros_de_acesso', 1);
        $this->travel(1)->minutes();
        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseCount('registros_de_acesso', 2);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.31'])->get('/dashboard')->assertOk();
        $this->assertDatabaseCount('registros_de_acesso', 3);
        $pessoa->delete();
        $this->assertDatabaseCount('registros_de_acesso', 3);
    }

    #[Test]
    public function ip_encaminhado_exige_proxy_confiavel_e_porta_do_proxy_nao_e_origem(): void
    {
        $pessoa = User::factory()->create();
        $proxies = Request::getTrustedProxies();
        $cabecalhos = Request::getTrustedHeaderSet();
        try {
            Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT);
            $pedido = Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.1', 'REMOTE_PORT' => '54321', 'HTTP_X_FORWARDED_FOR' => '2001:db8::30', 'HTTP_X_FORWARDED_PORT' => '443']);
            app(RegistrosDeAcesso::class)->registrar($pessoa, $pedido);
            $this->assertDatabaseHas('registros_de_acesso', ['ip' => '2001:db8::30', 'porta_origem' => null]);
            $direto = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.40', 'REMOTE_PORT' => '54322', 'HTTP_X_FORWARDED_FOR' => '192.0.2.99']);
            app(RegistrosDeAcesso::class)->registrar($pessoa, $direto);
            $this->assertDatabaseHas('registros_de_acesso', ['ip' => '192.0.2.40', 'porta_origem' => 54322]);
        } finally {
            Request::setTrustedProxies($proxies, $cabecalhos);
        }
    }

    #[Test]
    public function limpeza_em_lotes_preserva_limite_exato_de_seis_meses_e_registros_novos(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
        $linha = ['user_id' => 999999, 'ip' => '192.0.2.1', 'porta_origem' => null, 'ocorrido_em' => '2026-04-30 11:59:59'];
        DB::table('registros_de_acesso')->insert(array_fill(0, 1001, $linha));
        DB::table('registros_de_acesso')->insert([
            array_replace($linha, ['ocorrido_em' => '2026-04-30 12:00:00']),
            array_replace($linha, ['ocorrido_em' => '2026-10-31 12:00:00']),
        ]);
        $this->artisan('acessos:limpar-registros')->assertSuccessful();
        $this->assertDatabaseCount('registros_de_acesso', 2);
        $this->assertDatabaseMissing('registros_de_acesso', ['ocorrido_em' => '2026-04-30 11:59:59']);
    }
}
