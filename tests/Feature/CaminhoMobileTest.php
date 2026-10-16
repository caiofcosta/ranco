<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Navegacao\CaminhoMobile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CaminhoMobileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paginas_compartilham_um_unico_caminho_sem_duplicar_o_catalogo(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['mobile.pedidos', 'mobile.jogar-juntos', 'mobile.mais', 'jogar.index', 'jogar.labirinto', 'amigos.index', 'historico', 'perfil.mostrar', 'profile.edit', 'documentos.termos'] as $nome) {
            $resposta = $this->get(route($nome))->assertOk();
            $this->assertSame(1, substr_count($resposta->getContent(), 'data-mobile-breadcrumb'), $nome);
            $resposta->assertSee(route('mobile.inicio'), false);
        }
        $this->get(route('mobile.inicio'))->assertOk()->assertDontSee('data-mobile-breadcrumb', false);
    }

    #[Test]
    public function hierarquia_cobre_telas_internas_e_links_da_mesa_correta(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            ['jogar.labirinto.resultado', [], ['Início', 'Jogar sozinho', 'Labirinto', 'Resultado']],
            ['jogar.labirinto.tentativa', ['tentativa' => '123'], ['Início', 'Jogar sozinho', 'Labirinto', 'Jogar']],
            ['mesas.jogar', ['id' => 'mesa-teste'], ['Início', 'Jogar juntos', 'Mesa', 'Partida']],
            ['mesas.assistir', ['id' => 'mesa-teste'], ['Início', 'Jogar juntos', 'Mesa', 'Assistir']],
            ['mesas.encerrar', ['id' => 'mesa-teste'], ['Início', 'Jogar juntos', 'Mesa', 'Encerrar mesa']],
            ['mesas.sair', ['id' => 'mesa-teste'], ['Início', 'Jogar juntos', 'Mesa', 'Sair da mesa']],
            ['mesas.partida.encerrar', ['id' => 'mesa-teste'], ['Início', 'Jogar juntos', 'Mesa', 'Encerrar partida']],
            ['perfil.amigo', ['pessoa' => '123'], ['Início', 'Mais', 'Amigos', 'Perfil da amizade']],
            ['conta.participacoes-locais', [], ['Início', 'Mais', 'Perfil e conta', 'Participações locais']],
            ['settings.regras-aceitas', [], ['Início', 'Mais', 'Perfil e conta', 'Regras aceitas']],
            ['security.edit', [], ['Início', 'Mais', 'Configurações']],
        ] as [$nome, $parametros, $rotulos]) {
            $pedido = Request::create(route($nome, $parametros));
            $rota = clone Route::getRoutes()->getByName($nome);
            $rota->bind($pedido);
            $pedido->setRouteResolver(fn () => $rota);
            $this->app->instance('request', $pedido);
            $itens = app(CaminhoMobile::class)->itens();
            $this->assertSame($rotulos, array_column($itens, 'rotulo'), $nome);
            $this->assertNull($itens[array_key_last($itens)]['url']);
            if (isset($parametros['id'])) {
                $this->assertSame(route('mesas.mostrar', 'mesa-teste'), $itens[2]['url']);
            }
        }
    }

    #[Test]
    public function acesso_publico_tem_retorno_sem_exigir_conta(): void
    {
        foreach (['login', 'register', 'password.request', 'acesso-por-link', 'documentos.privacidade', 'documentos.conduta'] as $nome) {
            $resposta = $this->get(route($nome))->assertOk();
            $this->assertSame(1, substr_count($resposta->getContent(), 'data-mobile-breadcrumb'), $nome);
            $resposta->assertSee(route('home'), false);
        }
    }
}
