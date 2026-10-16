<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\Momento;
use App\Mesa\RankingDaMesa;
use App\Dominio\Provocacao\Provocacao;
use App\Infra\Banco\PdoRepositorioDeProvocacoes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MedalhasPorColocacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function empateNoSegundoDaPrataParaOsDoisEOTerceiroRecebeBronze(): void
    {
        $pessoas = $this->mesaCom('Ana', 'Bruno', 'Carla', 'Davi');
        $this->jogar($pessoas['mesa'], $pessoas['admin'], 1, [
            $pessoas['ids']['Ana'] => 10000,
            $pessoas['ids']['Bruno'] => 20000,
            $pessoas['ids']['Carla'] => 20000,
            $pessoas['ids']['Davi'] => 40000,
        ]);

        $medalhas = $this->porPessoa($pessoas['mesa']->id);
        $this->assertSame([1, 0, 'labirinto'], $medalhas[$pessoas['ids']['Ana']]);
        $this->assertSame([2, 1, 'labirinto'], $medalhas[$pessoas['ids']['Bruno']]);
        $this->assertSame([2, 1, 'labirinto'], $medalhas[$pessoas['ids']['Carla']]);
        $this->assertSame([3, 0, 'labirinto'], $medalhas[$pessoas['ids']['Davi']]);
        $this->assertContains(
            DB::table('medalhas')->where('pessoa', $pessoas['ids']['Davi'])->value('frase'),
            self::TERCEIRO,
        );
    }

    #[Test]
    public function empateNoTerceiroDaBronzeParaOsDois(): void
    {
        $pessoas = $this->mesaCom('Ana', 'Bruno', 'Carla', 'Davi');
        $this->jogar($pessoas['mesa'], $pessoas['admin'], 1, [
            $pessoas['ids']['Ana'] => 10000,
            $pessoas['ids']['Bruno'] => 20000,
            $pessoas['ids']['Carla'] => 30000,
            $pessoas['ids']['Davi'] => 30000,
        ]);

        $medalhas = $this->porPessoa($pessoas['mesa']->id);
        $this->assertSame([3, 1, 'labirinto'], $medalhas[$pessoas['ids']['Carla']]);
        $this->assertSame([3, 1, 'labirinto'], $medalhas[$pessoas['ids']['Davi']]);
        $this->assertCount(4, $medalhas);
    }

    #[Test]
    public function doisParticipantesRecebemOuroEPrata(): void
    {
        $pessoas = $this->mesaCom('Ana', 'Bruno');
        $this->jogar($pessoas['mesa'], $pessoas['admin'], 1, [
            $pessoas['ids']['Ana'] => 10000,
            $pessoas['ids']['Bruno'] => 20000,
        ]);

        $medalhas = $this->porPessoa($pessoas['mesa']->id);
        $this->assertSame([1, 0, 'labirinto'], $medalhas[$pessoas['ids']['Ana']]);
        $this->assertSame([2, 0, 'labirinto'], $medalhas[$pessoas['ids']['Bruno']]);
        $this->assertContains(
            DB::table('medalhas')->where('pessoa', $pessoas['ids']['Bruno'])->value('frase'),
            $this->frasesDeSegundo('Ana'),
        );
    }

    #[Test]
    public function empateNoPrimeiroDaOuroDuploEOProximoRecebePrata(): void
    {
        $pessoas = $this->mesaCom('Ana', 'Bruno', 'Carla');
        $mesa = $pessoas['mesa'];
        $this->abrir($mesa, $pessoas['admin'], 2);
        app(Plataforma::class)->naMesa($mesa->id, function (Mesa $m) use ($pessoas): void {
            $partida = $m->partidaAtiva();
            $admin = $pessoas['ids']['Ana'];
            $partida->pularVez($admin, $admin);
            $partida->iniciarTentativa($pessoas['ids']['Bruno']);
            $partida->concluirTentativa($pessoas['ids']['Bruno'], 10000);
            $partida->pularVez($pessoas['ids']['Carla'], $admin);
            $partida->abandonar($pessoas['ids']['Bruno']);
            $partida->iniciarTentativa($admin);
            $partida->concluirTentativa($admin, 10000);
            $partida->iniciarTentativa($pessoas['ids']['Carla']);
            $partida->concluirTentativa($pessoas['ids']['Carla'], 20000);
        });

        $medalhas = $this->porPessoa($mesa->id);
        $this->assertSame([1, 1, 'labirinto'], $medalhas[$pessoas['ids']['Ana']]);
        $this->assertSame([1, 1, 'labirinto'], $medalhas[$pessoas['ids']['Bruno']]);
        $this->assertSame([2, 0, 'labirinto'], $medalhas[$pessoas['ids']['Carla']]);
        $fraseDaAna = (string) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Ana'])->value('frase');
        $fraseDoBruno = (string) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Bruno'])->value('frase');
        $this->assertContains($fraseDaAna, $this->preencher(self::PRIMEIRO_EMPATE, 'Bruno'));
        $this->assertContains($fraseDoBruno, $this->preencher(self::PRIMEIRO_EMPATE, 'Ana'));
        $this->assertNotContains($fraseDaAna, $this->preencher([...self::SEGUNDO_EMPATE, ...self::TERCEIRO_EMPATE], 'Bruno'));
        $this->assertStringNotContainsString('{', $fraseDaAna);
        $this->assertStringNotContainsString($pessoas['ids']['Bruno'], $fraseDaAna);

        app(Plataforma::class)->mesa($mesa->id);
        $this->assertSame(1, DB::table('medalhas')->where('pessoa', $pessoas['ids']['Ana'])->count());
    }

    #[Test]
    public function momentosDeColocacaoExistemEOsDeEmpateOferecemOutro(): void
    {
        $this->assertSame('vencedor', DB::table('momentos_de_provocacao')->where('codigo', Momento::TerceiroLugar->value)->value('variaveis'));
        $this->assertSame('terceiro', DB::table('momentos_temas')->where('momento', Momento::TerceiroLugar->value)->value('tema'));
        $temas = [
            Momento::PrimeiroLugarEmpate->value => 'primeiro_empate',
            Momento::SegundoLugarEmpate->value => 'segundo_empate',
            Momento::TerceiroLugarEmpate->value => 'terceiro_empate',
        ];
        foreach ($temas as $momento => $tema) {
            $this->assertSame('outro', DB::table('momentos_de_provocacao')->where('codigo', $momento)->value('variaveis'));
            $this->assertSame([$tema], DB::table('momentos_temas')->where('momento', $momento)->pluck('tema')->all());
        }
    }

    #[Test]
    public function empateNoPrimeiroNaoUsaFraseDeSegundoOuTerceiro(): void
    {
        $repositorio = new PdoRepositorioDeProvocacoes(DB::connection()->getPdo());
        $doPrimeiro = array_map(
            static fn (Provocacao $frase): string => $frase->texto,
            $repositorio->candidatasPara(Momento::PrimeiroLugarEmpate),
        );
        sort($doPrimeiro);
        $esperado = self::PRIMEIRO_EMPATE;
        sort($esperado);
        $this->assertSame($esperado, $doPrimeiro);
        foreach ([...self::SEGUNDO_EMPATE, ...self::TERCEIRO_EMPATE] as $frase) {
            $this->assertNotContains($frase, $doPrimeiro);
        }
    }

    #[Test]
    public function empateNoSegundoComTresOutrosMostraAnaBrunoECarla(): void
    {
        $this->deixarSomente('segundo_empate', 'Você e {outro} no segundo lugar. Os dois acham que deveriam ter ganhado.');
        $pessoas = $this->mesaCom('Eva', 'Ana', 'Bruno', 'Carla', 'Davi');
        $this->jogar($pessoas['mesa'], $pessoas['admin'], 1, [
            $pessoas['ids']['Eva'] => 10000,
            $pessoas['ids']['Ana'] => 20000,
            $pessoas['ids']['Bruno'] => 20000,
            $pessoas['ids']['Carla'] => 20000,
            $pessoas['ids']['Davi'] => 20000,
        ]);

        $frase = (string) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Davi'])->value('frase');
        $this->assertSame('Você e Ana, Bruno e Carla no segundo lugar. Os dois acham que deveriam ter ganhado.', $frase);
        $this->assertSame(2, (int) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Davi'])->value('colocacao'));
        $this->assertSame(1, (int) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Davi'])->value('empate'));
    }

    #[Test]
    public function concessaoGravaONomeRealSemPlaceholder(): void
    {
        $this->deixarSomente('quase', 'Quase. {vencedor} ficou com o ouro, e você com a certeza de que foi perto.');
        $pessoas = $this->mesaCom('Ana', 'Bruno');
        $this->jogar($pessoas['mesa'], $pessoas['admin'], 1, [
            $pessoas['ids']['Ana'] => 10000,
            $pessoas['ids']['Bruno'] => 20000,
        ]);

        $frase = (string) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Bruno'])->value('frase');
        $this->assertSame('Quase. Ana ficou com o ouro, e você com a certeza de que foi perto.', $frase);
        $this->assertStringNotContainsString('{', $frase);
        $this->assertStringNotContainsString($pessoas['ids']['Ana'], $frase);
    }

    #[Test]
    public function concessaoUsaONomeDoParticipanteLocal(): void
    {
        $this->deixarSomente('quase', 'Quase. {vencedor} ficou com o ouro, e você com a certeza de que foi perto.');
        $ana = User::factory()->create(['name' => 'Ana']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $local = '';
        $plataforma->naMesa($mesa->id, function (Mesa $m) use ($ana, &$local): void {
            $local = $m->cadastrarParticipanteLocal('Duda', 'duda@example.test', $ana->identificadorNoDominio());
        });
        $this->jogar($mesa, $ana, 1, [
            $local => 10000,
            $ana->identificadorNoDominio() => 20000,
        ]);

        $frase = (string) DB::table('medalhas')->where('pessoa', $ana->identificadorNoDominio())->value('frase');
        $this->assertSame('Quase. Duda ficou com o ouro, e você com a certeza de que foi perto.', $frase);
        $this->assertStringNotContainsString($local, $frase);
    }

    #[Test]
    public function fraseGravadaMantemONomeDepoisDeRenomearOVencedor(): void
    {
        $this->deixarSomente('quase', 'Quase. {vencedor} ficou com o ouro, e você com a certeza de que foi perto.');
        $pessoas = $this->mesaCom('Ana', 'Bruno');
        $this->jogar($pessoas['mesa'], $pessoas['admin'], 1, [
            $pessoas['ids']['Ana'] => 10000,
            $pessoas['ids']['Bruno'] => 20000,
        ]);

        $ana = User::query()->findOrFail($pessoas['ids']['Ana']);
        $ana->update(['name' => 'Ana Renomeada']);
        $frase = (string) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Bruno'])->value('frase');
        $this->assertSame('Quase. Ana ficou com o ouro, e você com a certeza de que foi perto.', $frase);
        $this->assertStringNotContainsString('Ana Renomeada', $frase);

        $ana->delete();
        $this->assertSame(
            'Quase. Ana ficou com o ouro, e você com a certeza de que foi perto.',
            DB::table('medalhas')->where('pessoa', $pessoas['ids']['Bruno'])->value('frase'),
        );
    }

    #[Test]
    public function frasesDeEmpateUsamOutroComONomeDaOutraPessoa(): void
    {
        $this->deixarSomente('segundo_empate', 'Você e {outro} no segundo lugar. Os dois acham que deveriam ter ganhado.');
        $pessoas = $this->mesaCom('Ana', 'Bruno', 'Carla');
        $this->jogar($pessoas['mesa'], $pessoas['admin'], 1, [
            $pessoas['ids']['Ana'] => 10000,
            $pessoas['ids']['Bruno'] => 20000,
            $pessoas['ids']['Carla'] => 20000,
        ]);

        $fraseDoBruno = (string) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Bruno'])->value('frase');
        $fraseDaCarla = (string) DB::table('medalhas')->where('pessoa', $pessoas['ids']['Carla'])->value('frase');
        $this->assertSame('Você e Carla no segundo lugar. Os dois acham que deveriam ter ganhado.', $fraseDoBruno);
        $this->assertSame('Você e Bruno no segundo lugar. Os dois acham que deveriam ter ganhado.', $fraseDaCarla);
        $this->assertStringNotContainsString('{', $fraseDoBruno);
        $this->assertStringNotContainsString($pessoas['ids']['Carla'], $fraseDoBruno);
    }

    #[Test]
    public function empateNaMetaDaOuroDuploPrataETresPontos(): void
    {
        $this->conferirOuroDividido(3, null);
    }

    #[Test]
    public function empateNoLimiteDaOuroDuploPrataETresPontos(): void
    {
        $this->conferirOuroDividido(null, 1);
    }

    #[Test]
    public function quemSaiuComTotalMaiorLevaOOuroEOLiderAtivoFicaComPrata(): void
    {
        $pessoas = $this->mesaCom('Ana', 'Bruno', 'Carla');
        $ids = $pessoas['ids'];
        $this->abrir($pessoas['mesa'], $pessoas['admin'], 1);
        app(Plataforma::class)->naMesa($pessoas['mesa']->id, function (Mesa $mesa) use ($ids): void {
            $partida = $mesa->partidaAtiva();
            $partida->iniciarTentativa($ids['Ana']);
            $partida->concluirTentativa($ids['Ana'], 20000);
            $partida->iniciarTentativa($ids['Bruno']);
            $partida->concluirTentativa($ids['Bruno'], 10000);
            $partida->abandonar($ids['Bruno']);
            $partida->iniciarTentativa($ids['Carla']);
            $partida->concluirTentativa($ids['Carla'], 30000);
        });

        $mesa = app(Plataforma::class)->mesa($pessoas['mesa']->id);
        $medalhas = $this->porPessoa($mesa->id);
        $this->assertSame([$ids['Bruno']], $mesa->partidas()[0]->vencedores());
        $this->assertSame([1, 0, 'labirinto'], $medalhas[$ids['Bruno']]);
        $this->assertSame([2, 0, 'labirinto'], $medalhas[$ids['Ana']]);
        $this->assertSame([3, 0, 'labirinto'], $medalhas[$ids['Carla']]);
        $this->assertSame([
            $ids['Bruno'] => 3,
            $ids['Ana'] => 2,
            $ids['Carla'] => 1,
        ], RankingDaMesa::pontos($mesa));

        $this->actingAs($pessoas['admin'])->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Bruno venceu.', false);
    }

    #[Test]
    public function fraseDeDesempatePerdidoFicaInativa(): void
    {
        $texto = '{nome} perdeu no desempate para {vencedor}. Vice é só o primeiro dos perdedores.';

        $this->assertSame(0, (int) DB::table('provocacoes')->where('texto', $texto)->value('ativa'));
        $this->assertNotNull(
            DB::table('momentos_de_provocacao')->where('codigo', Momento::DesempatePerdido->value)->value('codigo'),
        );
    }

    #[Test]
    public function migracaoCadastraAsFrasesENaoDuplicaAsQueJaExistem(): void
    {
        $jaExistiam = [
            'Ganhou. Agora tenta disfarçar o sorriso.',
            'Quase levou o título. Um jeito bonito de dizer que não ganhou.',
        ];
        $novas = [
            'Venceu. A mesa inteira vai lembrar disso, e você também.',
            'Ganhou. Agora vai ouvir que foi sorte.',
            'Quase. {vencedor} ficou com o ouro, e você com a certeza de que foi perto.',
            'Segundo lugar. Faltou pouco, e {vencedor} vai lembrar que foi justo.',
            'Prata. Foi por pouco — e isso rende revanche.',
            ...self::TERCEIRO,
            ...self::PRIMEIRO_EMPATE,
            ...self::SEGUNDO_EMPATE,
            ...self::TERCEIRO_EMPATE,
        ];
        foreach ([...$jaExistiam, ...$novas] as $texto) {
            $this->assertSame(1, DB::table('provocacoes')->where('texto', $texto)->count(), $texto);
        }

        $migracao = require database_path('migrations/2026_10_03_000004_frases_das_medalhas_por_colocacao.php');
        $migracao->up();
        $ajuste = require database_path('migrations/2026_10_03_000005_temas_de_empate_por_colocacao.php');
        $ajuste->up();

        foreach ([...$jaExistiam, ...$novas] as $texto) {
            $this->assertSame(1, DB::table('provocacoes')->where('texto', $texto)->count(), $texto);
        }
        $this->assertNull(DB::table('temas_de_provocacao')->where('codigo', 'empate')->value('codigo'));
        $this->assertSame(
            'primeiro_empate',
            DB::table('provocacoes_temas')
                ->where('provocacao_id', DB::table('provocacoes')->where('texto', self::PRIMEIRO_EMPATE[0])->value('id'))
                ->value('tema'),
        );
        $this->assertSame(
            'vitoria',
            DB::table('provocacoes_temas')
                ->where('provocacao_id', DB::table('provocacoes')->where('texto', 'Ganhou. Agora tenta disfarçar o sorriso.')->value('id'))
                ->value('tema'),
        );
    }

    private function conferirOuroDividido(?int $meta, ?int $limite): void
    {
        $pessoas = $this->mesaCom('Ana', 'Bruno', 'Carla');
        $ids = $pessoas['ids'];
        $this->jogarAte($pessoas['mesa'], $pessoas['admin'], $meta, $limite, [
            $ids['Ana'] => 10000,
            $ids['Bruno'] => 10000,
            $ids['Carla'] => 20000,
        ]);

        $mesa = app(Plataforma::class)->mesa($pessoas['mesa']->id);
        $partida = $mesa->partidas()[0];
        $this->assertSame([$ids['Ana'], $ids['Bruno']], $partida->vencedores());
        $this->assertSame([
            $ids['Ana'] => [1, 1, 'labirinto'],
            $ids['Bruno'] => [1, 1, 'labirinto'],
            $ids['Carla'] => [2, 0, 'labirinto'],
        ], $this->porPessoa($mesa->id));
        $this->assertContains(
            (string) DB::table('medalhas')->where('pessoa', $ids['Ana'])->value('frase'),
            $this->preencher(self::PRIMEIRO_EMPATE, 'Bruno'),
        );
        $this->assertContains(
            (string) DB::table('medalhas')->where('pessoa', $ids['Carla'])->value('frase'),
            [
                'Quase levou o título. Um jeito bonito de dizer que não ganhou.',
                'Prata. Foi por pouco — e isso rende revanche.',
            ],
        );
        $this->assertSame([
            $ids['Ana'] => 3,
            $ids['Bruno'] => 3,
            $ids['Carla'] => 2,
        ], RankingDaMesa::pontos($mesa));

        $this->actingAs($pessoas['admin'])->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Ana e Bruno venceram.', false);

        app(Plataforma::class)->encerrarMesa($mesa->id, $pessoas['admin']->identificadorNoDominio());
        $this->actingAs($pessoas['admin'])->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Ana e Bruno', false);
    }

    /** @param array<string, int> $tempos */
    private function jogarAte(Mesa $mesa, User $admin, ?int $meta, ?int $limite, array $tempos): void
    {
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida(
            $mesa->id,
            CatalogoDeJogos::configuracao('labirinto', null, $meta, $limite),
            $admin->identificadorNoDominio(),
        );
        $plataforma->iniciarPartida($mesa->id, $admin->identificadorNoDominio());
        $plataforma->naMesa($mesa->id, static function (Mesa $m) use ($tempos): void {
            $partida = $m->partidaAtiva();
            $jogadas = 0;
            while (
                $partida->situacao()->value === 'em_andamento'
                && $partida->vezAtual() !== null
                && $jogadas < 12
            ) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
                $jogadas++;
            }
        });
    }

    /** @param array<string, int> $tempos */
    private function jogar(Mesa $mesa, User $admin, int $limiteRodadas, array $tempos): void
    {
        $this->abrir($mesa, $admin, $limiteRodadas);
        app(Plataforma::class)->naMesa($mesa->id, static function (Mesa $m) use ($tempos): void {
            $partida = $m->partidaAtiva();
            $rodada = $partida->rodadaAtual();
            while (
                $partida->situacao()->value === 'em_andamento'
                && $partida->rodadaAtual() === $rodada
                && $partida->vezAtual() !== null
            ) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });
    }

    private function abrir(Mesa $mesa, User $admin, int $limiteRodadas): void
    {
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida(
            $mesa->id,
            CatalogoDeJogos::configuracao('labirinto', null, null, $limiteRodadas),
            $admin->identificadorNoDominio(),
        );
        $plataforma->iniciarPartida($mesa->id, $admin->identificadorNoDominio());
    }

    /**
     * @param list<string> $nomes
     * @return array{mesa: Mesa, admin: User, ids: array<string, string>}
     */
    private function mesaCom(string ...$nomes): array
    {
        $admin = User::factory()->create(['name' => array_shift($nomes)]);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($admin->identificadorNoDominio(), 'Grupo');
        $ids = [$admin->name => $admin->identificadorNoDominio()];
        foreach ($nomes as $nome) {
            $pessoa = User::factory()->create(['name' => $nome]);
            $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($pessoa->identificadorNoDominio()));
            $ids[$nome] = $pessoa->identificadorNoDominio();
        }

        return ['mesa' => $mesa, 'admin' => $admin, 'ids' => $ids];
    }

    /** @return array<string, array{0: int, 1: int, 2: string}> */
    private function porPessoa(string $mesaId): array
    {
        $lista = [];
        foreach (DB::table('medalhas')->where('mesa_id', $mesaId)->get() as $medalha) {
            $lista[$medalha->pessoa] = [(int) $medalha->colocacao, (int) $medalha->empate, $medalha->jogo];
        }

        return $lista;
    }

    private function deixarSomente(string $tema, string $texto): void
    {
        $ids = DB::table('provocacoes')
            ->join('provocacoes_temas', 'provocacoes.id', '=', 'provocacoes_temas.provocacao_id')
            ->whereIn('provocacoes_temas.tema', $tema === 'quase' ? ['quase', 'segundo_lugar'] : [$tema])
            ->where('provocacoes.texto', '!=', $texto)
            ->pluck('provocacoes.id');
        DB::table('provocacoes')->whereIn('id', $ids)->update(['ativa' => false]);
    }

    /** @var list<string> */
    private const TERCEIRO = [
        'Bronze no peito. Na próxima, mira mais alto.',
        'Bronze. Segue o jogo, e o pódio também.',
        'Terceiro lugar: nem perto, nem longe. Só presente.',
    ];

    /** @var list<string> */
    private const PRIMEIRO_EMPATE = [
        'Empate no topo. Cada um vai contar essa história do seu jeito.',
        'Topo dividido. Cada um escolhe sua versão da história.',
        'Você e {outro} no topo, cada um convencido de que foi habilidade.',
    ];

    /** @var list<string> */
    private const SEGUNDO_EMPATE = [
        'Prata dividida. A revanche já ganhou assunto.',
        'Você e {outro} no segundo lugar. Os dois acham que deveriam ter ganhado.',
        'Empataram com {outro}. Ninguém ficou com a glória, só com a prata.',
    ];

    /** @var list<string> */
    private const TERCEIRO_EMPATE = [
        'Bronze dividido com {outro}. Os dois estão igualmente insatisfeitos.',
        'Você e {outro} no mesmo bronze. Pelo menos estão no mesmo lugar.',
        'Terceiro lugar empatado. O pódio ficou concorrido.',
    ];

    /**
     * @param list<string> $frases
     * @return list<string>
     */
    private function preencher(array $frases, string $outro): array
    {
        return array_map(
            static fn (string $frase): string => str_replace('{outro}', $outro, $frase),
            $frases,
        );
    }

    /** @return list<string> */
    private function frasesDeSegundo(string $vencedor): array
    {
        return [
            'Quase levou o título. Um jeito bonito de dizer que não ganhou.',
            "Quase. {$vencedor} ficou com o ouro, e você com a certeza de que foi perto.",
            "Segundo lugar. Faltou pouco, e {$vencedor} vai lembrar que foi justo.",
            'Prata. Foi por pouco — e isso rende revanche.',
        ];
    }
}
