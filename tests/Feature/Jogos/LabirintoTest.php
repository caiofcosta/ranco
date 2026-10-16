<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto;
use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Jogos\Labirinto\SoloLabirinto;
use App\Dominio\RegraViolada;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LabirintoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exige_login_e_email_verificado(): void
    {
        $this->get('/jogar')->assertRedirect('/login');
        $this->get('/jogar/labirinto')->assertRedirect('/login');
        $this->postJson('/jogar/labirinto/iniciar')->assertUnauthorized();
        $this->postJson('/jogar/labirinto/resultado')->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->get('/jogar/labirinto')->assertRedirect(route('verification.notice'));
        $this->postJson('/jogar/labirinto/iniciar')->assertForbidden();
    }

    #[Test]
    public function seed_tem_dez_mapas_validos_unicos_e_nao_sobrescreve_curadoria(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $this->assertDatabaseCount('mapas_labirinto', 10);
        $this->assertDatabaseCount('versoes_labirinto', 10);
        $this->assertDatabaseCount('desenhos_labirinto', 10);
        foreach (DB::table('versoes_labirinto')->get() as $versao) {
            DesenhoLabirinto::validarPublicacao(json_decode($versao->desenho, true)['linhas']);
            $this->assertNull($versao->publicador_id);
        }
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->listar()[0];
        $editado = $catalogo->salvar($mapa['id'], $mapa['revisao'], 'Nome escolhido por mim', $mapa['linhas'], false, null);
        $catalogo->alternarAtivo($editado['id'], $editado['revisao']);
        $this->seed(MapasLabirintoSeeder::class);
        $this->assertDatabaseCount('mapas_labirinto', 10);
        $this->assertDatabaseCount('versoes_labirinto', 10);
        $this->assertDatabaseHas('mapas_labirinto', ['id' => $mapa['id'], 'nome' => 'Nome escolhido por mim', 'ativo' => false, 'usos_em_rodadas' => null]);
    }

    #[Test]
    public function seed_reverte_lote_quando_existe_conflito_manual(): void
    {
        $dados = json_decode(file_get_contents(database_path('seeders/mapas-labirinto.json')), true)[4];
        app(CatalogoDeMapasLabirinto::class)->salvar(null, 0, $dados['nome'], $dados['linhas'], true, null);
        try {
            $this->seed(MapasLabirintoSeeder::class);
            $this->fail('Deveria informar conflito');
        } catch (RegraViolada) {
            $this->assertDatabaseCount('mapas_labirinto', 1);
            $this->assertDatabaseCount('versoes_labirinto', 1);
        }
    }

    #[Test]
    public function lista_mostra_nome_logo_do_catalogo_e_link_do_labirinto(): void
    {
        $nome = CatalogoDeJogos::nome(CatalogoDeJogos::LABIRINTO);
        $logo = CatalogoDeJogos::logo(CatalogoDeJogos::LABIRINTO);

        $this->actingAs(User::factory()->create())
            ->get(route('jogar.index'))
            ->assertOk()
            ->assertSee($nome)
            ->assertSee('src="'.asset((string) $logo).'"', false)
            ->assertSee('alt=""', false)
            ->assertSee('href="'.route('jogar.labirinto').'"', false);
    }

    #[Test]
    public function escolha_mostra_previa_e_acao_de_cada_mapa(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $resposta = $this->actingAs(User::factory()->create())->get('/jogar/labirinto')->assertOk()
            ->assertSee('Jogar mapa aleatório')->assertSee('Não jogado');
        $mapas = DB::table('mapas_labirinto')->pluck('id');

        $this->assertSame($mapas->count(), substr_count($resposta->getContent(), 'viewBox="0 0 13 13"'));
        foreach ($mapas as $id) {
            $resposta->assertSee('name="mapa" value="'.$id.'"', false);
        }
    }

    #[Test]
    public function catalogo_vazio_nao_inicia_e_nao_mostra_rascunho_ou_inativo(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/jogar/labirinto')->assertOk()->assertSee('Ainda não há mapas');
        $this->postJson('/jogar/labirinto/iniciar')->assertUnprocessable();
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Rascunho oculto', DesenhoLabirinto::vazio(), false, null);
        $this->postJson('/jogar/labirinto/iniciar', ['mapa' => $mapa['id']])->assertUnprocessable();
        $this->seed(MapasLabirintoSeeder::class);
        $inativo = DB::table('mapas_labirinto')->whereNotNull('codigo_seed')->first();
        $catalogo->alternarAtivo($inativo->id, $inativo->revisao);
        $this->get('/jogar/labirinto')->assertDontSee('Rascunho oculto')->assertDontSee($inativo->nome);
        $this->postJson('/jogar/labirinto/iniciar', ['mapa' => $inativo->id])->assertUnprocessable();
    }

    #[Test]
    public function sorteia_uma_versao_ativa_e_recarregar_nao_troca_mapa(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $this->actingAs(User::factory()->create());
        $resposta = $this->post('/jogar/labirinto/iniciar')->assertRedirect();
        $destino = $resposta->headers->get('Location');
        $primeiro = $this->get($destino)->assertOk()->viewData('jogo');
        $segundo = $this->get($destino)->assertOk()->viewData('jogo');
        $this->assertSame($primeiro->versao_id, $segundo->versao_id);
        $this->assertDatabaseCount('tentativas_solo_labirinto', 1);
    }

    #[Test]
    public function salva_por_pessoa_e_por_versao_sem_alterar_legado_ou_uso_em_rodadas(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $pessoa = User::factory()->create();
        $this->actingAs($pessoa);
        $mapa = app(CatalogoDeMapasLabirinto::class)->listar()[0];
        $a = $this->iniciar($mapa['id']);
        $this->travel(30)->seconds();
        $salvo = $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $a, 'tempo' => 15000])->assertOk();
        $this->get($salvo->json('destino'))->assertOk()->assertSee('15,000 s')->assertSee('Tempo atual')->assertSee('Seu recorde')->assertSee('Histórico')
            ->assertSee('10 melhores')->assertDontSee('10 últimas')->assertDontSee('Seu recorde nesta versão')
            ->assertSee(now('America/Sao_Paulo')->format('d/m/Y'))->assertSee('Jogar de novo')->assertSee('Histórico');
        $this->assertDatabaseHas('tentativas_solo_labirinto', ['id' => $a, 'tempo' => 15000, 'recorde' => true]);
        $data = DB::table('tentativas_solo_labirinto')->where('id', $a)->value('concluida_em');
        $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $a, 'tempo' => 15000])->assertOk();
        $this->assertDatabaseHas('tentativas_solo_labirinto', ['id' => $a, 'concluida_em' => $data]);
        $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $a, 'tempo' => 16000])->assertConflict();
        foreach ([15000, 16000, 14000] as $tempo) {
            $id = $this->iniciar($mapa['id']);
            $this->travel(30)->seconds();
            $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $id, 'tempo' => $tempo])->assertOk();
            $this->assertDatabaseHas('tentativas_solo_labirinto', ['id' => $id, 'recorde' => $tempo === 14000]);
        }
        $this->get('/jogar/labirinto')->assertSee('14,000 s');
        $this->assertDatabaseCount('melhores_resultados', 0);
        $this->assertSame(0, DB::table('mapas_labirinto')->whereNotNull('usos_em_rodadas')->count());
        $this->actingAs(User::factory()->create())->get('/jogar/labirinto')->assertDontSee('14,000 s');
        $this->get('/jogar/labirinto/resultado?tentativa='.$a)->assertNotFound();
        $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $a, 'tempo' => 15000])->assertNotFound();
    }

    #[Test]
    public function publicacao_e_desativacao_nao_mudam_tentativa_em_andamento(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $pessoa = User::factory()->create();
        $this->actingAs($pessoa);
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->listar()[0];
        $antiga = $this->iniciar($mapa['id']);
        $nova = $catalogo->salvar($mapa['id'], $mapa['revisao'], 'Novo nome', $mapa['linhas'], true, null);
        $atual = $this->iniciar($mapa['id']);
        $catalogo->alternarAtivo($nova['id'], $nova['revisao']);
        $this->get(route('jogar.labirinto.tentativa', $antiga))->assertSee($mapa['nome'])->assertDontSee('Novo nome');
        $this->travel(30)->seconds();
        foreach ([$antiga, $atual] as $id) {
            $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $id, 'tempo' => 15000])->assertOk();
            $this->assertDatabaseHas('tentativas_solo_labirinto', ['id' => $id, 'recorde' => true]);
        }
        $this->assertNotSame(DB::table('tentativas_solo_labirinto')->where('id', $antiga)->value('versao_id'), DB::table('tentativas_solo_labirinto')->where('id', $atual)->value('versao_id'));
    }

    #[Test]
    public function valida_tempos_com_base_no_mapa_e_rejeita_tentativa_ausente_ou_expirada(): void
    {
        $this->actingAs(User::factory()->create());
        $linhas = DesenhoLabirinto::vazio();
        $linhas[1] = '#IC.........#';
        $mapa = app(CatalogoDeMapasLabirinto::class)->salvar(null, 0, 'Curto', $linhas, true, null);
        $id = $this->iniciar($mapa['id']);
        foreach ([null, 0, -1, 1, 5000.5, 'abc', [], true, 86400001, 15000] as $tempo) {
            $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $id, 'tempo' => $tempo])->assertUnprocessable()->assertJsonValidationErrors('tempo');
        }
        $this->travel(2)->seconds();
        $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $id, 'tempo' => 300])->assertOk();
        $this->postJson('/jogar/labirinto/resultado', ['tempo' => 300])->assertUnprocessable()->assertJsonValidationErrors('tentativa');
        $expirada = $this->iniciar($mapa['id']);
        $this->travel(\App\Dominio\Jogos\Labirinto\TentativaSolo::VALIDADE_SEGUNDOS + 1)->seconds();
        $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $expirada, 'tempo' => 300])->assertUnprocessable();
    }

    #[Test]
    public function historico_limita_dez_por_mapa_versao_e_pessoa(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $pessoa = User::factory()->create();
        $this->actingAs($pessoa);
        $mapas = app(CatalogoDeMapasLabirinto::class)->listar();
        $solo = app(SoloLabirinto::class);
        for ($i = 0; $i < 12; $i++) {
            $id = $this->iniciar($mapas[0]['id']);
            $this->travel(30)->seconds();
            $solo->concluir($pessoa->id, $id, 15000 + $i);
        }
        $outro = $this->iniciar($mapas[1]['id']);
        $this->travel(30)->seconds();
        $solo->concluir($pessoa->id, $outro, 10000);
        $historico = $solo->historico($pessoa->id, (int) $solo->buscar($pessoa->id, $id)->versao_id);
        $this->assertCount(10, $historico['melhores']);
        $this->assertCount(10, $historico['ultimas']);
        $this->assertSame(15000, $historico['melhorTempo']);
        $this->assertSame(15011, (int) $historico['ultimas'][0]->tempo);
        $this->assertSame(15000, (int) $historico['melhores'][0]->tempo);
    }

    #[Test]
    public function previa_exige_admin_valida_caminho_e_nao_registra_jogo(): void
    {
        $pessoa = User::factory()->create();
        $this->actingAs($pessoa)->postJson(route('painel.labirinto.previa'), ['mapa' => '{}'])->assertForbidden();
        config(['ranco.administradores' => [mb_strtolower($pessoa->email)]]);
        $dados = ['formato' => 1, 'nome' => 'Teste', 'linhas' => DesenhoLabirinto::vazio()];
        $this->postJson(route('painel.labirinto.previa'), ['mapa' => json_encode($dados)])->assertUnprocessable();
        $dados['linhas'][1] = '#IC.........#';
        $this->post(route('painel.labirinto.previa'), ['mapa' => json_encode($dados)])->assertOk()->assertSee('Teste do administrador')->assertSee('data-previa="true"', false)->assertSee('data-destino=""', false);
        $this->assertDatabaseCount('tentativas_solo_labirinto', 0);
        $this->assertDatabaseCount('mapas_labirinto', 0);
        $this->assertDatabaseCount('melhores_resultados', 0);
    }

    #[Test]
    public function resultado_nao_oferece_atalho_para_mesas(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $pessoa = User::factory()->create();
        app(\App\Dominio\Plataforma\Plataforma::class)->criarMesa($pessoa->identificadorNoDominio());
        $this->actingAs($pessoa);
        $tentativa = $this->iniciar(app(CatalogoDeMapasLabirinto::class)->listar()[0]['id']);
        $this->travel(30)->seconds();

        $destino = $this->postJson('/jogar/labirinto/resultado', ['tentativa' => $tentativa, 'tempo' => 15000])->assertOk()->json('destino');

        $this->get($destino)->assertOk()->assertDontSee('Ver suas mesas')->assertDontSee('Criar mesa')->assertSee('Jogar de novo');
    }

    private function iniciar(int $mapa): string
    {
        $resposta = $this->post('/jogar/labirinto/iniciar', ['mapa' => $mapa])->assertRedirect();

        return basename($resposta->headers->get('Location'));
    }
}
