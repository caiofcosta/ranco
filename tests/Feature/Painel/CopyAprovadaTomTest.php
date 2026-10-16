<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;
use Database\Seeders\OpcoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CopyAprovadaTomTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_08_000001_aplicar_copy_aprovada_do_tom.php');
    }

    #[Test]
    public function banco_novo_recebe_todos_os_textos_e_preserva_contexto_do_convite(): void
    {
        $migration = $this->migration();
        $this->assertCount(51, $migration::FRASES);
        $this->assertCount(6, $migration::OPCOES);
        foreach ($migration::FRASES as $antes => $depois) {
            $this->assertDatabaseMissing('provocacoes', ['texto' => $antes]);
            $this->assertDatabaseHas('provocacoes', ['texto' => $depois]);
        }
        foreach ($migration::OPCOES as $antes => $depois) {
            $this->assertDatabaseMissing('opcoes', ['lista' => 'nomes_de_mesa', 'rotulo' => $antes]);
            $this->assertDatabaseHas('opcoes', ['lista' => 'nomes_de_mesa', 'rotulo' => $depois]);
        }
        $this->assertStringContainsString('admin', DB::table('momentos_de_provocacao')->where('codigo', 'inicio.convite_pendente')->value('variaveis'));
        $this->assertDatabaseHas('provocacoes', ['texto' => 'Já são {tentativa} erros. Seus amigos estão rindo e você nem começou a jogar.']);
    }

    #[Test]
    public function atualiza_so_texto_preservando_curadoria_vinculos_ids_e_seeds_repetidos(): void
    {
        $migration = $this->migration();
        $origem = 'Errou logo de primeira. Começamos bem.';
        DB::table('provocacoes')->where('id', 2)->update(['texto' => $origem, 'ativa' => false]);
        DB::table('provocacoes')->where('id', 1)->update(['texto' => 'Curadoria particular: '.$origem]);
        DB::table('opcoes')->where('valor', 'familia-infelizmente')->update(['rotulo' => 'Família (infelizmente)', 'ativo' => false]);
        DB::table('opcoes')->where('valor', 'primos-do-caos')->update(['rotulo' => 'Nome personalizado']);
        $frases = DB::table('provocacoes')->orderBy('id')->get()->keyBy('id')->toArray();
        $opcoes = DB::table('opcoes')->orderBy('id')->get()->keyBy('id')->toArray();
        $vinculos = DB::table('provocacoes_temas')->orderBy('provocacao_id')->orderBy('tema')->get()->toArray();
        $frases[2]->texto = $migration::FRASES[$origem];
        foreach ($opcoes as $opcao) {
            if ($opcao->valor === 'familia-infelizmente') {
                $opcao->rotulo = 'Família em jogo';
            }
        }
        $migration->up();
        $migration->up();
        (new OpcoesSeeder)->run();
        $this->assertEquals($frases, DB::table('provocacoes')->orderBy('id')->get()->keyBy('id')->toArray());
        $this->assertEquals($opcoes, DB::table('opcoes')->orderBy('id')->get()->keyBy('id')->toArray());
        $this->assertEquals($vinculos, DB::table('provocacoes_temas')->orderBy('provocacao_id')->orderBy('tema')->get()->toArray());
    }

    #[Test]
    public function frases_especificas_nao_vazam_para_outros_contextos(): void
    {
        $repositorio = app(RepositorioDeProvocacoes::class);
        $textos = fn ($momento) => array_map(fn ($frase) => $frase->texto, $repositorio->candidatasPara($momento));
        $momentos = Momento::class;
        $this->assertContains('A senha está fazendo jogo duro.', $textos($momentos::ErroDeLogin));
        $this->assertNotContains('A senha está fazendo jogo duro.', $textos($momentos::TentativaInterrompida));
        $this->assertContains('O plano era outro. A próxima jogada está em aberto.', $textos($momentos::TentativaInterrompida));
        $this->assertNotContains('O plano era outro. A próxima jogada está em aberto.', $textos($momentos::ErroDeLogin));
        $this->assertContains('Prata. Foi por pouco — e isso rende revanche.', $textos($momentos::SegundoLugar));
        $this->assertNotContains('Prata. Foi por pouco — e isso rende revanche.', $textos($momentos::DesempatePerdido));
        $this->assertContains('Intervalo técnico. Daqui a pouco tem revanche.', $textos($momentos::PausaDeLogin));
        $this->assertContains('Intervalo técnico. Daqui a pouco tem revanche.', $textos($momentos::PausadaPeloAdmin));
        $this->assertSame(2, (int) DB::table('provocacoes')->where('texto', 'O plano era outro. A próxima jogada está em aberto.')->value('intensidade'));
        $this->assertSame([1, 2, 3], DB::table('provocacoes')->whereIn('id', range(1, 9))->distinct()->orderBy('intensidade')->pluck('intensidade')->map(fn ($n) => (int) $n)->all());
        $this->assertCount(0, DB::table('provocacoes')->select('texto')->groupBy('texto')->havingRaw('COUNT(*) > 1')->get());
        $this->assertTrue(collect(DB::select('SHOW INDEX FROM provocacoes'))->contains(fn ($index) => $index->Key_name === 'provocacoes_texto_unique' && (int) $index->Non_unique === 0));
    }
}
