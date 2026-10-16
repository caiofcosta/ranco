<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Plataforma\Plataforma;
use App\Filament\Resources\Opcoes\Pages\GerenciarOpcoes;
use App\Livewire\Mesa\FormularioCriarMesa;
use App\Models\Opcao;
use App\Models\User;
use App\Opcoes\Lista;
use Database\Seeders\NomesDeMesaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SugestoesDeNomeTest extends TestCase
{
    use RefreshDatabase;

    private function entrar(): User
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        return $usuario;
    }

    #[Test]
    public function abreComDezSugestoesGenericas(): void
    {
        $this->entrar();

        $this->get(route('mesas.formulario'))
            ->assertOk()
            ->assertSee('Qual é a turma?')
            ->assertSeeLivewire(FormularioCriarMesa::class);

        $tela = Livewire::test(FormularioCriarMesa::class)
            ->assertSet('categoria', 'generico');

        $sugestoes = $tela->get('sugestoes');
        $this->assertCount(10, $sugestoes);

        $this->assertSame(
            ['generico'],
            Opcao::whereIn('id', array_column($sugestoes, 'id'))
                ->distinct()
                ->pluck('categoria')
                ->all(),
        );
    }

    #[Test]
    public function aceitaAsSeisCategoriasESoOfereceRegistrosAtivosDaCategoria(): void
    {
        $this->entrar();

        foreach (array_keys(Opcao::CATEGORIAS_NOMES_DE_MESA) as $categoria) {
            $inativa = Opcao::where('categoria', $categoria)->firstOrFail();
            $inativa->update(['ativo' => false]);

            $tela = Livewire::test(FormularioCriarMesa::class)
                ->set('categoria', $categoria)
                ->assertHasNoErrors();

            $sugestoes = $tela->get('sugestoes');
            $ids = array_column($sugestoes, 'id');

            $this->assertCount(10, $sugestoes);
            $this->assertNotContains($inativa->id, $ids);
            $this->assertSame(
                10,
                Opcao::whereIn('id', $ids)
                    ->where('categoria', $categoria)
                    ->where('ativo', true)
                    ->count(),
            );
        }
    }

    #[Test]
    public function rejeitaCategoriaInvalida(): void
    {
        $this->entrar();

        Livewire::test(FormularioCriarMesa::class)
            ->set('categoria', 'inexistente')
            ->assertHasErrors(['categoria'])
            ->assertSet('sugestoes', []);
    }

    #[Test]
    public function renovarEvitaOsDezRegistrosAnteriores(): void
    {
        $this->entrar();

        $tela = Livewire::test(FormularioCriarMesa::class)
            ->set('categoria', 'familia');

        $anteriores = array_column($tela->get('sugestoes'), 'id');

        $tela->call('outrasSugestoes')->assertHasNoErrors();

        $novas = array_column($tela->get('sugestoes'), 'id');

        $this->assertCount(10, $novas);
        $this->assertSame([], array_values(array_intersect($anteriores, $novas)));
    }

    #[Test]
    public function turmaComPoucasOpcoesNaoDuplicaEContinuaPodendoRenovar(): void
    {
        $this->entrar();

        $ids = Opcao::where('categoria', 'casal')->limit(4)->pluck('id');

        Opcao::where('categoria', 'casal')
            ->whereNotIn('id', $ids)
            ->update(['ativo' => false]);

        $tela = Livewire::test(FormularioCriarMesa::class)
            ->set('categoria', 'casal');

        $this->assertCount(4, $tela->get('sugestoes'));

        $tela->call('outrasSugestoes');

        $this->assertCount(4, $tela->get('sugestoes'));
        $this->assertCount(4, array_unique(array_column($tela->get('sugestoes'), 'id')));

        Opcao::where('categoria', 'casal')->update(['ativo' => false]);

        $tela->call('outrasSugestoes')
            ->assertSet('sugestoes', [])
            ->assertSee('Nenhuma sugestão nesta turma.');
    }

    #[Test]
    public function selecionarPreencheONomeSemCriarMesaEPermiteEditar(): void
    {
        $usuario = $this->entrar();

        $tela = Livewire::test(FormularioCriarMesa::class);
        $sugestao = $tela->get('sugestoes')[0];

        $tela->call('selecionar', $sugestao['id'])
            ->assertHasNoErrors()
            ->assertSet('nome', $sugestao['nome'])
            ->set('nome', 'Nome editado')
            ->call('outrasSugestoes')
            ->assertSet('nome', 'Nome editado')
            ->set('categoria', 'empresa')
            ->assertSet('nome', 'Nome editado');

        $this->assertSame(
            [],
            app(Plataforma::class)->mesasAbertasDe($usuario->identificadorNoDominio()),
        );
    }

    #[Test]
    public function naoSelecionaSugestaoDesativadaDepoisDoSorteio(): void
    {
        $this->entrar();

        $tela = Livewire::test(FormularioCriarMesa::class);
        $id = $tela->get('sugestoes')[0]['id'];

        Opcao::whereKey($id)->update(['ativo' => false]);

        $tela->call('selecionar', $id)
            ->assertHasErrors(['sugestao'])
            ->assertSet('nome', '');
    }

    #[Test]
    public function exigeContaAutenticadaEEmailVerificado(): void
    {
        $this->get(route('mesas.formulario'))->assertRedirect(route('login'));

        Livewire::test(FormularioCriarMesa::class)->assertUnauthorized();

        $this->actingAs(User::factory()->unverified()->create());

        Livewire::test(FormularioCriarMesa::class)->assertForbidden();
    }

    #[Test]
    public function catalogoTemCinquentaNomesPorCategoriaESementePreservaCuradoria(): void
    {
        foreach (NomesDeMesaSeeder::NOMES as $categoria => $nomes) {
            $this->assertCount(50, $nomes);
            $this->assertCount(50, array_unique($nomes));

            foreach ($nomes as $nome) {
                $this->assertLessThanOrEqual(40, mb_strlen($nome));

                $this->assertSame(
                    1,
                    Opcao::where('lista', Lista::NomesDeMesa->value)
                        ->where('categoria', $categoria)
                        ->where('rotulo', $nome)
                        ->count(),
                );
            }
        }

        $total = Opcao::count();

        $editada = Opcao::where('valor', 'familia-treta-de-domingo')->sole();
        $editada->update(['rotulo' => 'Domingo personalizado', 'ativo' => false]);

        (new NomesDeMesaSeeder)->run();
        (new NomesDeMesaSeeder)->run();

        $this->assertSame($total, Opcao::count());
        $this->assertSame('Domingo personalizado', $editada->fresh()->rotulo);
        $this->assertFalse($editada->fresh()->ativo);
        $this->assertDatabaseHas('opcoes', ['valor' => 'primos-do-caos']);
    }

    #[Test]
    public function painelPermiteCadastrarUmaSugestaoComTurma(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']));
        Filament::setCurrentPanel('painel');

        Livewire::test(GerenciarOpcoes::class)
            ->callAction('create', data: [
                'lista' => Lista::NomesDeMesa->value,
                'categoria' => 'familia',
                'rotulo' => 'Domingo dos vizinhos',
                'valor' => 'domingo-dos-vizinhos',
                'ordem' => 200,
                'ativo' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('opcoes', [
            'valor' => 'domingo-dos-vizinhos',
            'categoria' => 'familia',
            'ativo' => true,
        ]);
    }
}
