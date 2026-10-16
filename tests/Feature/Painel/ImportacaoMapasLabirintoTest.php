<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto;
use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Jogos\Labirinto\IdentidadeMapaLabirinto;
use App\Dominio\RegraViolada;
use App\Filament\Pages\MapasLabirinto;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImportacaoMapasLabirintoTest extends TestCase
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

    private static function linhas(): array
    {
        $linhas = DesenhoLabirinto::vazio();
        $linhas[1][1] = 'I';
        $linhas[11][11] = 'C';

        return $linhas;
    }

    #[Test]
    public function importar_em_mapa_aberto_cria_previa_independente_e_sem_alterar_original(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $original = $catalogo->salvar(null, 0, 'Original', self::linhas(), true, $this->admin->id);
        $novo = self::linhas();
        $novo[5][5] = '#';
        Livewire::test(MapasLabirinto::class)->call('abrir', $original['id'])
            ->set('jsonImportacao', json_encode(['formato' => 1, 'nome' => 'Importado', 'linhas' => $novo]))
            ->call('importar')->assertHasNoErrors()->assertSet('mapaId', null)
            ->assertSet('linhas', $novo)->assertSet('ativo', false)->assertSet('historico', []);
        $this->assertSame($original, $catalogo->buscar($original['id']));
        $this->assertDatabaseCount('mapas_labirinto', 1);
    }

    #[Test]
    public function importacao_revalida_permissao(): void
    {
        $pagina = Livewire::test(MapasLabirinto::class);
        config(['ranco.administradores' => []]);
        $pagina->call('importar')->assertStatus(403);
        $this->assertDatabaseCount('mapas_labirinto', 0);
    }

    #[Test]
    public function migracao_detecta_nomes_legados_duplicados_antes_de_alterar_estrutura(): void
    {
        // Simula duas linhas legadas: a migração recalcula a identidade pelo nome.
        foreach (['legado-1', 'legado-2'] as $chave) {
            DB::table('mapas_labirinto')->insert([
                'nome' => 'Repetido', 'chave_nome' => hash('sha256', $chave),
                'rascunho' => json_encode(self::linhas()),
            ]);
        }
        $migracao = require database_path('migrations/2026_10_06_000007_garantir_identidade_dos_mapas_labirinto.php');
        try {
            $migracao->up();
            $this->fail('Duplicatas legadas deveriam interromper a migração.');
        } catch (\RuntimeException $erro) {
            $this->assertStringContainsString('nomes repetidos', $erro->getMessage());
        }
        $this->assertDatabaseCount('mapas_labirinto', 2);
    }

    #[Test]
    public function novo_limpa_json_inclusive_apos_erro_e_importar_preserva_texto_para_comparacao(): void
    {
        $json = json_encode(['formato' => 1, 'nome' => 'Prévia', 'linhas' => self::linhas()]);
        $pagina = Livewire::test(MapasLabirinto::class)->set('jsonImportacao', $json)
            ->call('importar')->assertHasNoErrors()->assertSet('jsonImportacao', $json)
            ->call('novo')->assertSet('jsonImportacao', '')->assertSet('nome', '')
            ->assertSet('mapaId', null)->assertSet('linhas', DesenhoLabirinto::vazio());
        $pagina->set('jsonImportacao', '{')->call('importar')->assertHasErrors('jsonImportacao')
            ->call('novo')->assertHasNoErrors()->assertSet('jsonImportacao', '')
            ->assertSet('mapaRepetidoId', null);
        $this->assertDatabaseCount('mapas_labirinto', 0);
    }

    #[Test]
    public function importa_sem_gravar_permite_editar_e_salvar_com_uso_nulo(): void
    {
        $pagina = Livewire::test(MapasLabirinto::class)->set('jsonImportacao', json_encode([
            'formato' => 1, 'nome' => '  Beco Novo  ', 'linhas' => self::linhas(),
        ]))->call('importar')->assertHasNoErrors()->assertSet('mapaId', null)
            ->assertSet('nome', 'Beco Novo')->assertSet('linhas', self::linhas())
            ->assertSet('versaoPublicada', null)->assertSet('ativo', false);
        $this->assertDatabaseCount('mapas_labirinto', 0);
        $pagina->set('ferramenta', '#')->call('pintar', 5, 5)->call('salvar')->assertHasNoErrors();
        $this->assertDatabaseHas('mapas_labirinto', ['nome' => 'Beco Novo', 'usos_em_rodadas' => null, 'ativo' => false]);
        $this->assertDatabaseCount('versoes_labirinto', 0);
    }

    #[Test]
    #[DataProvider('jsonsInvalidos')]
    public function erro_de_importacao_preserva_editor(string $json): void
    {
        Livewire::test(MapasLabirinto::class)->set('nome', 'Meu rascunho')
            ->set('jsonImportacao', $json)->call('importar')->assertHasErrors('jsonImportacao')
            ->assertSet('nome', 'Meu rascunho')->assertSet('linhas', DesenhoLabirinto::vazio());
        $this->assertDatabaseCount('mapas_labirinto', 0);
    }

    public static function jsonsInvalidos(): array
    {
        $base = ['formato' => 1, 'nome' => 'Rascunho', 'linhas' => self::linhas()];
        $bloqueado = self::linhas();
        $bloqueado[1][2] = '#';
        $bloqueado[2][1] = '#';
        $borda = self::linhas();
        $borda[0][1] = '.';

        return [
            ['{'], ['[]'], ['null'], [str_repeat(' ', 8193)],
            [json_encode(array_replace($base, ['formato' => '1']))],
            [json_encode(array_replace($base, ['nome' => ' ']))],
            [json_encode(array_replace($base, ['nome' => str_repeat('a', 81)]))],
            [json_encode(array_replace($base, ['linhas' => ['#']]))],
            [json_encode(array_replace($base, ['linhas' => $bloqueado]))],
            [json_encode(array_replace($base, ['linhas' => $borda]))],
            [json_encode($base + ['mapa_id' => 123])],
            [json_encode(array_replace($base, ['linhas' => DesenhoLabirinto::vazio()]))],
        ];
    }

    #[Test]
    public function repetido_indica_mapa_existente_sem_substituir_editor(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Original', self::linhas(), true, $this->admin->id);
        $pagina = Livewire::test(MapasLabirinto::class)->set('nome', 'Não perder')
            ->set('jsonImportacao', json_encode(['formato' => 1, 'nome' => 'Outro', 'linhas' => self::linhas()]))
            ->call('importar')->assertHasErrors('jsonImportacao')->assertSet('nome', 'Não perder')
            ->assertSet('mapaRepetidoId', $mapa['id'])->assertSee('Abrir mapa existente');
        $pagina->call('abrir', $mapa['id'])->assertSet('mapaId', $mapa['id'])->assertSet('nome', 'Original');
        $this->assertDatabaseCount('mapas_labirinto', 1);
    }

    #[Test]
    public function nome_e_desenho_sao_independentes_mesmo_em_mapa_inativo(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $original = $catalogo->salvar(null, 0, 'BÉCO', self::linhas(), true, $this->admin->id);
        $original = $catalogo->alternarAtivo($original['id'], $original['revisao']);
        $outras = self::linhas();
        $outras[5][5] = '#';
        foreach ([['  béco  ', $outras], ['Nome diferente', self::linhas()]] as [$nome, $linhas]) {
            try {
                $catalogo->salvar(null, 0, $nome, $linhas, false, $this->admin->id);
                $this->fail('Duplicata foi aceita.');
            } catch (RegraViolada $erro) {
                $this->assertStringContainsString('já pertence', $erro->getMessage());
            }
        }
        $this->assertDatabaseCount('mapas_labirinto', 1);
        $outro = $catalogo->salvar(null, 0, 'Novo desafio', $outras, false, $this->admin->id);
        $this->assertNotSame($original['id'], $outro['id']);
        $catalogo->salvar($original['id'], $original['revisao'], 'BÉCO', self::linhas(), true, $this->admin->id);
        $this->assertDatabaseCount('versoes_labirinto', 2);
    }

    #[Test]
    public function reserva_versao_antiga_mas_libera_rascunho_nunca_publicado(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $mapa = $catalogo->salvar(null, 0, 'Histórico', self::linhas(), true, $this->admin->id);
        $rascunho = self::linhas();
        $rascunho[5][5] = '#';
        $mapa = $catalogo->salvar($mapa['id'], $mapa['revisao'], 'Histórico', $rascunho, false, $this->admin->id);
        $novo = $rascunho;
        $novo[5][6] = '#';
        $catalogo->salvar($mapa['id'], $mapa['revisao'], 'Histórico', $novo, false, $this->admin->id);
        $this->assertNotNull($catalogo->conflito(null, 'Outro', self::linhas()));
        $this->assertNull($catalogo->conflito(null, 'Outro', $rascunho));
        $this->assertDatabaseCount('desenhos_labirinto', 2);
    }

    #[Test]
    public function indice_do_banco_impede_nome_repetido_sem_depender_da_tela(): void
    {
        app(CatalogoDeMapasLabirinto::class)->salvar(null, 0, 'Único', self::linhas(), false, $this->admin->id);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('mapas_labirinto')->insert([
            'nome' => 'ÚNICO', 'chave_nome' => IdentidadeMapaLabirinto::chaveNome('ÚNICO'),
            'rascunho' => json_encode(self::linhas()),
        ]);
    }

    #[Test]
    public function indice_do_banco_impede_reservar_desenho_para_outro_mapa(): void
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);
        $catalogo->salvar(null, 0, 'Um', self::linhas(), false, $this->admin->id);
        $outras = self::linhas();
        $outras[5][5] = '#';
        $outro = $catalogo->salvar(null, 0, 'Dois', $outras, false, $this->admin->id);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('desenhos_labirinto')->insert(['hash' => IdentidadeMapaLabirinto::chaveDesenho(self::linhas()), 'mapa_id' => $outro['id']]);
    }
}
