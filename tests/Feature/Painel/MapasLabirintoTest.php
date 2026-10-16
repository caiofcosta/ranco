<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto;
use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\RegraViolada;
use App\Filament\Pages\MapasLabirinto;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MapasLabirintoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->admin = User::factory()->create(['email' => 'admin@exemplo.com']);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('painel');
    }

    #[Test]
    public function so_administrador_verificado_acessa_e_permissao_eh_revalidada(): void
    {
        $this->get('/painel/jogos/labirinto/mapas')->assertOk();
        $pagina = Livewire::test(MapasLabirinto::class);

        config(['ranco.administradores' => []]);
        $pagina->call('salvar')->assertStatus(403);
        $this->assertDatabaseCount('mapas_labirinto', 0);

        $this->actingAs(User::factory()->create());
        $this->get('/painel/jogos/labirinto/mapas')->assertForbidden();
        Livewire::test(MapasLabirinto::class)->assertStatus(403);

        config(['ranco.administradores' => [$this->admin->email]]);
        $this->admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->admin);
        Livewire::test(MapasLabirinto::class)->assertStatus(403);
    }

    #[Test]
    public function editor_desenha_desfaz_e_publica_uma_versao(): void
    {
        $pagina = Livewire::test(MapasLabirinto::class)
            ->set('nome', 'Beco do Arrependimento')
            ->set('ferramenta', 'I')->call('pintar', 1, 1)
            ->set('ferramenta', 'C')->call('pintar', 11, 11)
            ->set('ferramenta', '#')->call('pintar', 5, 5)
            ->call('desfazer');

        $linhas = $pagina->get('linhas');
        $this->assertSame('.', $linhas[5][5]);

        $pagina->call('salvar', true)->assertHasNoErrors()
            ->assertSet('versaoPublicada', 1)->assertSet('ativo', true);

        $this->assertDatabaseCount('mapas_labirinto', 1);
        $this->assertDatabaseCount('versoes_labirinto', 1);
        $versao = DB::table('versoes_labirinto')->first();
        $this->assertSame($linhas, json_decode($versao->desenho, true)['linhas']);
        $this->assertSame((int) $this->admin->id, (int) $versao->publicador_id);
    }

    #[Test]
    public function salva_rascunho_incompleto_mas_nao_publica(): void
    {
        Livewire::test(MapasLabirinto::class)
            ->set('nome', 'Ainda perdido')
            ->call('salvar')->assertHasNoErrors()
            ->call('salvar', true)->assertHasErrors('mapa');

        $this->assertDatabaseCount('mapas_labirinto', 1);
        $this->assertDatabaseCount('versoes_labirinto', 0);
        $this->assertDatabaseHas('mapas_labirinto', ['ativo' => false, 'versao_publicada' => null]);
    }

    #[Test]
    public function nova_publicacao_preserva_anterior_e_desativacao_preserva_todas(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Primeiro nome', $this->valido(), true, $this->admin->id);
        $anterior = DB::table('versoes_labirinto')->first();

        $linhas = $this->valido();
        $linhas[5][5] = '#';
        $mapa = $catalogo->salvar($mapa['id'], $mapa['revisao'], 'Segundo nome', $linhas, true, $this->admin->id);

        $this->assertEquals($anterior, DB::table('versoes_labirinto')->where('numero', 1)->first());
        $this->assertSame(2, $mapa['versao_publicada']);

        $mapa = $catalogo->alternarAtivo($mapa['id'], $mapa['revisao']);
        $this->assertFalse($mapa['ativo']);
        $this->assertDatabaseCount('versoes_labirinto', 2);
        $this->assertCount(2, $catalogo->versoes($mapa['id']));
    }

    #[Test]
    public function revisao_antiga_nao_sobrescreve_nem_publica_nem_desativa(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $original = $catalogo->salvar(null, 0, 'Original', $this->valido(), true, $this->admin->id);
        $atual = $catalogo->salvar($original['id'], $original['revisao'], 'Revisado', $this->valido(), false, $this->admin->id);

        foreach (['salvar', 'publicar', 'desativar'] as $acao) {
            try {
                if ($acao === 'desativar') {
                    $catalogo->alternarAtivo($original['id'], $original['revisao']);
                } else {
                    $catalogo->salvar($original['id'], $original['revisao'], 'Obsoleto', $this->valido(), $acao === 'publicar', $this->admin->id);
                }
                $this->fail('Uma revisão antiga não pode alterar o mapa.');
            } catch (RegraViolada $erro) {
                $this->assertStringContainsString('outra aba', $erro->getMessage());
            }
        }

        $this->assertSame($atual, $catalogo->buscar($original['id']));
        $this->assertDatabaseCount('versoes_labirinto', 1);
    }

    #[Test]
    public function duplicar_cria_rascunho_independente_sem_versoes(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $original = $catalogo->salvar(null, 0, 'Original', $this->valido(), true, $this->admin->id);

        Livewire::test(MapasLabirinto::class)
            ->call('abrir', $original['id'])
            ->call('duplicar')
            ->assertSet('mapaId', null)
            ->assertSet('versaoPublicada', null)
            ->call('salvar')->assertHasErrors('mapa')
            ->call('pintar', 5, 5)
            ->call('salvar')->assertHasNoErrors();

        $this->assertDatabaseCount('mapas_labirinto', 2);
        $this->assertDatabaseCount('versoes_labirinto', 1);
        $this->assertDatabaseHas('mapas_labirinto', [
            'nome' => 'Original (cópia)', 'ativo' => false, 'versao_publicada' => null,
        ]);
        $this->assertSame($original, $catalogo->buscar($original['id']));
    }

    #[Test]
    #[DataProvider('propriedadesProtegidas')]
    public function navegador_nao_pode_alterar_estado_protegido(string $propriedade, mixed $valor): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(MapasLabirinto::class)->set($propriedade, $valor);
    }

    public static function propriedadesProtegidas(): array
    {
        return [
            ['mapaId', 999],
            ['revisao', 999],
            ['ativo', true],
            ['versaoPublicada', 999],
            ['linhas', []],
            ['historico', [['adulterado']]],
            ['mapaRepetidoId', 999],
        ];
    }

    #[Test]
    public function ignora_coordenadas_e_ferramenta_invalidas_sem_alterar_historico(): void
    {
        $pagina = Livewire::test(MapasLabirinto::class);
        foreach ([[-1, 1], [0, 1], [12, 1], [13, 1], [1, -1], [1, 0], [1, 12], [1, 13]] as [$linha, $coluna]) {
            $pagina->call('pintar', $linha, $coluna);
        }
        $pagina->set('ferramenta', 'X')->call('pintar', 1, 1)
            ->assertSet('linhas', DesenhoLabirinto::vazio())->assertSet('historico', []);
    }

    #[Test]
    public function mover_inicio_e_chegada_remove_marca_anterior_e_desfazer_restaura(): void
    {
        $pagina = Livewire::test(MapasLabirinto::class)
            ->set('ferramenta', 'I')->call('pintar', 1, 1)
            ->set('ferramenta', 'C')->call('pintar', 11, 11);
        $original = $pagina->get('linhas');
        $pagina->set('ferramenta', 'I')->call('pintar', 2, 2)
            ->set('ferramenta', 'C')->call('pintar', 10, 10);
        $linhas = $pagina->get('linhas');
        $this->assertSame('.', $linhas[1][1]);
        $this->assertSame('.', $linhas[11][11]);
        $this->assertSame('I', $linhas[2][2]);
        $this->assertSame('C', $linhas[10][10]);
        $this->assertSame(1, substr_count(implode('', $linhas), 'I'));
        $this->assertSame(1, substr_count(implode('', $linhas), 'C'));
        $pagina->call('desfazer')->call('desfazer')->assertSet('linhas', $original);
    }

    #[Test]
    public function desfazer_mantem_somente_as_ultimas_cinquenta_alteracoes(): void
    {
        $pagina = Livewire::test(MapasLabirinto::class);
        $aposPrimeira = null;
        for ($i = 0; $i < 51; $i++) {
            $pagina->call('pintar', intdiv($i, 11) + 1, ($i % 11) + 1);
            if ($i === 0) {
                $aposPrimeira = $pagina->get('linhas');
            }
        }
        $this->assertCount(50, $pagina->get('historico'));
        for ($i = 0; $i < 50; $i++) {
            $pagina->call('desfazer');
        }
        $pagina->assertSet('linhas', $aposPrimeira)->assertSet('historico', [])
            ->call('desfazer')->assertSet('linhas', $aposPrimeira);
    }

    #[Test]
    public function recusa_nomes_invalidos_sem_gravar_mapa_ou_versao(): void
    {
        foreach (['', '   ', str_repeat('a', 81)] as $nome) {
            Livewire::test(MapasLabirinto::class)->set('nome', $nome)
                ->set('ferramenta', 'I')->call('pintar', 1, 1)
                ->set('ferramenta', 'C')->call('pintar', 11, 11)
                ->call('salvar')->assertHasErrors('mapa')
                ->call('salvar', true)->assertHasErrors('mapa');
        }
        $this->assertDatabaseCount('mapas_labirinto', 0);
        $this->assertDatabaseCount('versoes_labirinto', 0);
    }

    #[Test]
    public function so_ativa_versao_publicada_e_reativacao_preserva_historico(): void
    {
        $pagina = Livewire::test(MapasLabirinto::class)->set('nome', 'Ativação')
            ->call('salvar')->call('alternarAtivo')->assertHasErrors('mapa')
            ->assertSet('ativo', false)->assertSet('versaoPublicada', null)
            ->set('ferramenta', 'I')->call('pintar', 1, 1)
            ->set('ferramenta', 'C')->call('pintar', 11, 11)
            ->call('salvar', true)->assertHasNoErrors();
        $versao = DB::table('versoes_labirinto')->first();
        $pagina->call('alternarAtivo')->assertSet('ativo', false)
            ->call('alternarAtivo')->assertHasNoErrors()->assertSet('ativo', true);
        $this->assertDatabaseCount('versoes_labirinto', 1);
        $this->assertEquals($versao, DB::table('versoes_labirinto')->first());
    }

    #[Test]
    public function conflito_na_pagina_preserva_edicao_e_permite_salvar_copia(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Original', $this->valido(), true, $this->admin->id);
        $pagina = Livewire::test(MapasLabirinto::class)->call('abrir', $mapa['id'])
            ->set('nome', 'Minha edição')->call('pintar', 5, 5);
        $edicao = $pagina->get('linhas');
        $atual = $catalogo->alternarAtivo($mapa['id'], $mapa['revisao']);

        $pagina->call('salvar')->assertHasErrors('mapa')->assertSee('Duplicar rascunho')
            ->assertSet('linhas', $edicao)->assertSet('nome', 'Minha edição')
            ->call('salvar', true)->assertHasErrors('mapa')
            ->call('alternarAtivo')->assertHasErrors('mapa');
        $this->assertSame($atual, $catalogo->buscar($mapa['id']));
        $pagina->call('duplicar')->call('salvar')->assertHasNoErrors();
        $copia = $catalogo->buscar($pagina->get('mapaId'));
        $this->assertSame($edicao, $copia['linhas']);
        $this->assertFalse($copia['ativo']);
        $this->assertDatabaseCount('mapas_labirinto', 2);
        $this->assertDatabaseCount('versoes_labirinto', 1);
    }

    #[Test]
    public function catalogo_identifica_previa_do_rascunho_separada_da_publicacao(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Publicado', $this->valido(), true, $this->admin->id);
        $rascunho = $this->valido();
        $rascunho[5][5] = '#';
        $catalogo->salvar($mapa['id'], $mapa['revisao'], 'Rascunho revisado', $rascunho, false, $this->admin->id);
        Livewire::test(MapasLabirinto::class)
            ->assertSee('Prévia do rascunho')->assertSee('Publicação: ativa · versão 1');
        $this->assertSame($this->valido(), json_decode(DB::table('versoes_labirinto')->first()->desenho, true)['linhas']);
    }

    #[Test]
    public function novo_mapa_tem_uso_nulo_e_lista_nao_mostra_zero(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Ainda não medido', $this->valido(), true, $this->admin->id);
        $this->assertNull($mapa['usos_em_rodadas']);
        $this->assertNull($catalogo->listar()[0]['usos_em_rodadas']);
        $this->assertDatabaseHas('mapas_labirinto', ['id' => $mapa['id'], 'usos_em_rodadas' => null]);
        Livewire::test(MapasLabirinto::class)->assertSee('Uso em rodadas: Ainda não registrado');
    }

    #[Test]
    #[DataProvider('contagensDeUso')]
    public function leitura_preserva_zero_e_edicao_nao_altera_contagem(?int $usos, string $rotulo): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Medido', $this->valido(), true, $this->admin->id);
        // Simula o registro futuro do jogo; o painel não edita estatísticas.
        DB::table('mapas_labirinto')->where('id', $mapa['id'])->update(['usos_em_rodadas' => $usos]);
        $this->assertSame($usos, $catalogo->buscar($mapa['id'])['usos_em_rodadas']);
        $this->assertSame($usos, $catalogo->listar()[0]['usos_em_rodadas']);
        $pagina = Livewire::test(MapasLabirinto::class)->assertSee('Uso em rodadas: '.$rotulo)
            ->call('abrir', $mapa['id'])->set('nome', 'Revisado')
            ->call('salvar')->assertHasNoErrors()
            ->call('salvar', true)->assertHasNoErrors()
            ->call('alternarAtivo')->assertHasNoErrors();
        $this->assertSame($usos, $catalogo->buscar($mapa['id'])['usos_em_rodadas']);
        $pagina->call('duplicar')->call('pintar', 5, 5)->call('salvar')->assertHasNoErrors();
        $this->assertNull($catalogo->buscar($pagina->get('mapaId'))['usos_em_rodadas']);
    }

    public static function contagensDeUso(): array
    {
        return [[null, 'Ainda não registrado'], [0, '0'], [7, '7']];
    }

    /** @return list<string> */
    private function valido(): array
    {
        $linhas = DesenhoLabirinto::vazio();
        $linhas[1][1] = 'I';
        $linhas[11][11] = 'C';

        return $linhas;
    }
}
