<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Acesso\Aceites;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegrasAceitasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['testes.aceite_automatico' => false]);
        View::addLocation(base_path('tests/Fixtures/views'));
    }

    #[Test]
    public function lista_so_os_proprios_aceites_em_ordem_com_horario_de_brasilia_e_links_versionados(): void
    {
        $pessoa = User::factory()->create();
        $outra = User::factory()->create();
        $this->aceitar($pessoa, '2026-10-05', '2026-10-06 01:42:00');
        $this->aceitar($pessoa, '2026-11-05', '2026-11-06 02:10:00');
        $this->aceitar($outra, '2026-10-05', '2026-10-20 12:34:00');
        $resposta = $this->actingAs($pessoa)->get(route('settings.regras-aceitas', ['user_id' => $outra->id]))
            ->assertOk()->assertSeeInOrder([
                'Versão de 5 de novembro de 2026, aceita em 05/11/2026 às 23:10',
                'Versão de 5 de outubro de 2026, aceita em 05/10/2026 às 22:42',
            ])->assertDontSee('20/10/2026');
        $this->assertTrue($resposta->headers->hasCacheControlDirective('private'));
        $this->assertTrue($resposta->headers->hasCacheControlDirective('no-store'));
        foreach (['termos', 'privacidade', 'conduta'] as $documento) {
            $this->get(route('settings.regras-aceitas'))->assertSee(route('documentos.'.$documento, ['versao' => '2026-10-05']), false)
                ->assertSee(route('documentos.'.$documento, ['versao' => '2026-11-05']), false);
        }
        $this->actingAs($outra)->get(route('settings.regras-aceitas'))->assertOk()
            ->assertSee('20/10/2026 às 09:34')->assertDontSee('05/11/2026')->assertDontSee('05/10/2026 às 22:42');
        $this->get('/settings/regras-aceitas/'.$pessoa->id)->assertNotFound();
        $this->get('/settings/profile')->assertSee('Regras aceitas')->assertSee(route('settings.regras-aceitas'), false);
    }

    #[Test]
    public function nova_versao_muda_documentos_vigentes_mas_preserva_os_antigos_e_exige_novo_aceite(): void
    {
        $pessoa = User::factory()->create();
        $this->aceitar($pessoa, '2026-10-05', '2026-10-05 23:42:00');
        $antigos = [];
        foreach (['termos', 'privacidade', 'conduta'] as $documento) {
            $antigos[$documento] = $this->textoDoDocumento('/'.$documento.'/2026-10-05');
            $this->assertSame($antigos[$documento], $this->textoDoDocumento('/'.$documento));
        }
        // Simula o próximo deploy: a constante segue como padrão em produção.
        $this->app->instance(Aceites::class, new Aceites('2026-11-05'));
        $this->actingAs($pessoa)->get(route('settings.regras-aceitas'))->assertRedirect(route('aceite.mostrar'));
        $this->get('/aceite')->assertSee('As regras mudaram. Leia e aceite a nova versão para continuar.')
            ->assertSee(route('documentos.termos', ['versao' => '2026-11-05']), false);
        foreach (['termos', 'privacidade', 'conduta'] as $documento) {
            $this->get('/'.$documento)->assertOk()->assertSee('versão fictícia de novembro para teste');
            $this->assertSame($antigos[$documento], $this->textoDoDocumento('/'.$documento.'/2026-10-05'));
        }
        $this->post('/aceite', ['confirmacao' => '1', 'versao' => '2026-10-05'])->assertSessionHasErrors('versao');
        $this->post('/aceite', ['confirmacao' => '1', 'versao' => '2026-11-05'])->assertSessionHasNoErrors()->assertRedirect(route('settings.regras-aceitas'));
        $this->assertDatabaseHas('aceites', ['user_id' => $pessoa->id, 'versao' => '2026-11-05']);
        $this->get(route('settings.regras-aceitas'))->assertOk()->assertSee('/termos/2026-10-05', false)->assertSee('/termos/2026-11-05', false);
    }

    #[Test]
    public function visitante_vai_ao_login_e_conta_pendente_ao_aceite(): void
    {
        $this->get(route('settings.regras-aceitas'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('settings.regras-aceitas'))->assertRedirect(route('aceite.mostrar'));
        $this->getJson(route('settings.regras-aceitas'))->assertForbidden()->assertJsonStructure(['mensagem']);
        foreach (['termos', 'privacidade', 'conduta'] as $documento) {
            $this->get('/'.$documento.'/2026-10-05')->assertOk();
        }
    }

    #[Test]
    public function versao_inexistente_ou_caminho_invalido_retorna_404(): void
    {
        foreach (['termos', 'privacidade', 'conduta'] as $documento) {
            foreach (['2026-01-01', '2026-99-99', 'qualquer', '2026-10-05.termos'] as $versao) {
                $this->get('/'.$documento.'/'.$versao)->assertNotFound();
            }
        }
    }

    private function textoDoDocumento(string $url): string
    {
        $html = $this->get($url)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<article[^>]*>(.*?)<\/article>/s', $html, $partes));

        return $partes[1];
    }

    private function aceitar(User $pessoa, string $versao, string $data): void
    {
        $pessoa->aceites()->create(['versao' => $versao, 'aceito_em' => $data, 'ip' => '192.0.2.1']);
    }
}
