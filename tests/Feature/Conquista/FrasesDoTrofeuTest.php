<?php

declare(strict_types=1);

namespace Tests\Feature\Conquista;

use App\Conquista\ConcedeTrofeuDaMesa;
use App\Dominio\Conquista\ConcessorDeTrofeus;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class FrasesDoTrofeuTest extends TestCase
{
    use RefreshDatabase;

    private const VITORIA = 'Levou a mesa. O resto da turma já está inventando desculpa.';

    private const EMPATE = 'Topo da mesa dividido com {outro}. Cada um vai contar essa história do seu jeito.';

    /** @var list<string> */
    private const FRASES_DE_VITORIA = [
        self::VITORIA,
        'Primeiro lugar da mesa inteira. Alguém precisava, e foi você.',
        'A mesa acabou, e o topo é seu. Vai ser difícil ser modesto com isso.',
    ];

    /** @var list<string> */
    private const FRASES_DE_EMPATE = [
        'Topo da mesa dividido com {outro}. Cada um vai contar essa história do seu jeito.',
        'Empate no primeiro lugar com {outro}. Ninguém ganhou sozinho, e ninguém vai deixar isso quieto.',
        'Você e {outro} ficaram juntos no topo da mesa. Sorte de um, habilidade do outro, e vice-versa.',
    ];

    #[Test]
    public function vitoriaGravaFraseDoMomentoCertoSemPlaceholder(): void
    {
        $this->deixarSomente('trofeu_vitoria', self::VITORIA);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = $this->mesaCom($ana, $bruno);
        $this->jogar($mesa, [$ana->identificadorNoDominio() => 5000, $bruno->identificadorNoDominio() => 8000]);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $frase = (string) DB::table('trofeus')->where('pessoa', $ana->identificadorNoDominio())->value('frase');
        $this->assertSame(self::VITORIA, $frase);
        $this->assertStringNotContainsString('{', $frase);
        foreach (self::FRASES_DE_EMPATE as $deEmpate) {
            $this->assertStringNotContainsString('Topo da mesa dividido', $frase);
            $this->assertNotSame($deEmpate, $frase);
        }
    }

    #[Test]
    public function empateUsaONomeDaOutraPessoaENaoUsaFraseDeVitoria(): void
    {
        $this->deixarSomente('trofeu_empate', self::EMPATE);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = $this->mesaCom($ana, $bruno);
        $this->jogar($mesa, [$ana->identificadorNoDominio() => 5000, $bruno->identificadorNoDominio() => 5000]);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $frase = (string) DB::table('trofeus')->where('pessoa', $ana->identificadorNoDominio())->value('frase');
        $this->assertSame(
            'Topo da mesa dividido com Bruno. Cada um vai contar essa história do seu jeito.',
            $frase,
        );
        $this->assertStringNotContainsString('{', $frase);
        $this->assertStringNotContainsString($bruno->identificadorNoDominio(), $frase);
        foreach (self::FRASES_DE_VITORIA as $deVitoria) {
            $this->assertNotSame($deVitoria, $frase);
        }
    }

    #[Test]
    public function empateEmSegundoUsaFraseDeEmpateSemNomesETerceiroSozinhoUsaAComum(): void
    {
        $empateNoSegundo = 'Empate no segundo lugar. O pódio ficou apertado, mas coube todo mundo.';
        $terceiroSozinho = 'Terceiro lugar. Ninguém vai lembrar do placar, mas o troféu fica na estante.';
        $this->deixarSomente('trofeu_segundo_empate', $empateNoSegundo);
        $this->deixarSomente('trofeu_terceiro', $terceiroSozinho);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $dani = User::factory()->create(['name' => 'Dani']);
        $mesa = $this->mesaCom($ana, $bruno, $carla, $dani);
        $this->jogar($mesa, [
            $ana->identificadorNoDominio() => 5000,
            $bruno->identificadorNoDominio() => 7000,
            $carla->identificadorNoDominio() => 7000,
            $dani->identificadorNoDominio() => 9000,
        ]);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        foreach ([$bruno, $carla] as $empatado) {
            $trofeu = DB::table('trofeus')->where('pessoa', $empatado->identificadorNoDominio())->first();
            $this->assertSame('segundo', $trofeu->tipo);
            $this->assertSame($empateNoSegundo, $trofeu->frase);
        }
        $trofeuDaDani = DB::table('trofeus')->where('pessoa', $dani->identificadorNoDominio())->first();
        $this->assertSame('terceiro', $trofeuDaDani->tipo);
        $this->assertSame($terceiroSozinho, $trofeuDaDani->frase);
    }

    #[Test]
    public function empateNoTopoNaoCitaQuemFicouEmSegundo(): void
    {
        $this->deixarSomente('trofeu_empate', self::EMPATE);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $mesa = $this->mesaCom($ana, $bruno, $carla);
        $this->jogar($mesa, [
            $ana->identificadorNoDominio() => 5000,
            $bruno->identificadorNoDominio() => 5000,
            $carla->identificadorNoDominio() => 8000,
        ]);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $this->assertSame(
            'Topo da mesa dividido com Bruno. Cada um vai contar essa história do seu jeito.',
            DB::table('trofeus')->where('pessoa', $ana->identificadorNoDominio())->value('frase'),
        );
        $this->assertSame('segundo', DB::table('trofeus')->where('pessoa', $carla->identificadorNoDominio())->value('tipo'));
    }

    #[Test]
    public function empateComTresOutrosMostraAnaBrunoECarla(): void
    {
        $this->deixarSomente('trofeu_empate', self::EMPATE);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $davi = User::factory()->create(['name' => 'Davi']);
        $mesa = $this->mesaCom($ana, $bruno, $carla, $davi);
        $tempo = 5000;
        $tempos = [];
        foreach ([$ana, $bruno, $carla, $davi] as $pessoa) {
            $tempos[$pessoa->identificadorNoDominio()] = $tempo;
        }
        $this->jogar($mesa, $tempos);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $this->assertSame(
            'Topo da mesa dividido com Ana, Bruno e Carla. Cada um vai contar essa história do seu jeito.',
            DB::table('trofeus')->where('pessoa', $davi->identificadorNoDominio())->value('frase'),
        );
    }

    #[Test]
    public function fraseGravadaPermaneceDepoisDeRenomear(): void
    {
        $this->deixarSomente('trofeu_empate', self::EMPATE);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = $this->mesaCom($ana, $bruno);
        $this->jogar($mesa, [$ana->identificadorNoDominio() => 5000, $bruno->identificadorNoDominio() => 5000]);
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $bruno->update(['name' => 'Bruno Renomeado']);

        $this->assertSame(
            'Topo da mesa dividido com Bruno. Cada um vai contar essa história do seu jeito.',
            DB::table('trofeus')->where('pessoa', $ana->identificadorNoDominio())->value('frase'),
        );
    }

    #[Test]
    public function semNomeNaoPreencheAVariavelEGravaFraseNula(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $metodo = new ReflectionMethod(ConcedeTrofeuDaMesa::class, 'fraseDe');
        $premiados = [
            ['pessoa' => $ana->identificadorNoDominio(), 'tipo' => 'empate'],
            ['pessoa' => 'sem-conta', 'tipo' => 'empate'],
        ];

        $this->assertNull($metodo->invoke(app(ConcedeTrofeuDaMesa::class), $mesa, $premiados, $premiados[0]));
    }

    #[Test]
    public function trofeuJaConcedidoNaoGanhaFraseDepois(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        DB::table('trofeus')->insert([
            'pessoa' => $ana->identificadorNoDominio(),
            'mesa_id' => $mesa->id,
            'tipo' => 'vitoria',
            'mesa_nome' => 'Grupo',
            'frase' => null,
            'concedido_em' => '2026-10-01 12:00:00.000',
            'mesa_criada_em' => null,
        ]);

        app(ConcessorDeTrofeus::class)->concederPendentes($plataforma->mesa($mesa->id));

        $this->assertNull(DB::table('trofeus')->where('mesa_id', $mesa->id)->value('frase'));
    }

    /** @param array<string, int> $tempos */
    private function jogar(Mesa $mesa, array $tempos): void
    {
        $admin = $mesa->admin();
        app(Plataforma::class)->naMesa($mesa->id, function (Mesa $mesa) use ($admin, $tempos): void {
            $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, 1), $admin);
            $partida->iniciar($admin);
            while ($partida->situacao()->value === 'em_andamento' && $partida->vezAtual() !== null) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });
    }

    private function mesaCom(User $admin, User ...$outros): Mesa
    {
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($admin->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static function (Mesa $mesa) use ($outros): void {
            foreach ($outros as $outro) {
                $mesa->adicionarMembro($outro->identificadorNoDominio());
            }
        });

        return $plataforma->mesa($mesa->id);
    }

    private function deixarSomente(string $tema, string $texto): void
    {
        $ids = DB::table('provocacoes')
            ->join('provocacoes_temas', 'provocacoes.id', '=', 'provocacoes_temas.provocacao_id')
            ->where('provocacoes_temas.tema', $tema)
            ->where('provocacoes.texto', '!=', $texto)
            ->pluck('provocacoes.id');
        DB::table('provocacoes')->whereIn('id', $ids)->update(['ativa' => false]);
    }
}
