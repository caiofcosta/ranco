<?php

declare(strict_types=1);

namespace Tests\Feature\Banco;

use App\Dominio\ConflitoDeConcorrencia;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Infra\Banco\PdoRepositorioDeConvites;
use App\Infra\Banco\PdoRepositorioDeMesas;
use App\Infra\Banco\PdoRepositorioSocial;
use App\Infra\Banco\TransacaoDoLaravel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\RelogioFalso;
use Tests\Support\JogosSempreDisponiveis;
use Tests\TestCase;

/**
 * Repositórios do jogo no MariaDB de testes. Cada teste roda numa transação desfeita no final.
 */
final class BancoTest extends TestCase
{
    use RefreshDatabase;

    private PDO $pdo;

    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DB::connection()->getPdo();
        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-01 10:00:00'));
    }

    #[Test]
    public function partidaGravadaContinuaJogavelDepoisDeRecarregada(): void
    {
        $mesa = $this->plataforma()->criarMesa('ana');
        $this->plataforma()->naMesa($mesa->id, static function (Mesa $m): void {
            $m->cadastrarParticipanteLocal('Bruno', 'bruno@exemplo.com', 'ana');
            $m->novaPartida(new ConfiguracaoPartida(2, 6, 300), 'ana')->iniciar('ana');
            $m->partidaAtiva()?->iniciarTentativa('ana');
            $m->partidaAtiva()?->concluirTentativa('ana', 10);
        });

        $recarregada = $this->plataforma()->mesa($mesa->id);
        $local = $recarregada->participantesLocais()[0]->id;
        $this->assertSame($local, $recarregada->partidaAtiva()?->vezAtual());

        $this->plataforma()->naMesa($mesa->id, static function (Mesa $m) use ($local): void {
            $m->partidaAtiva()?->iniciarTentativa($local);
            $m->partidaAtiva()?->concluirTentativa($local, 20);
        });

        $final = $this->plataforma()->mesa($mesa->id)->partidaAtiva();
        $this->assertSame(2, $final?->rodadaAtual());
        $this->assertSame(3, $final?->totalDe($local));
    }

    #[Test]
    public function prazosVencidosSaoProcessadosPeloCronEGravadosComoEventos(): void
    {
        $mesa = $this->plataforma()->criarMesa('ana');
        $this->plataforma()->naMesa($mesa->id, static function (Mesa $m): void {
            $m->cadastrarParticipanteLocal('Bruno', 'bruno@exemplo.com', 'ana');
            $m->novaPartida(new ConfiguracaoPartida(2, 6, 300), 'ana')->iniciar('ana');
        });

        $this->relogio->avancar(300);
        $this->assertGreaterThanOrEqual(1, $this->plataforma()->processarPrazosDeTodas());

        $recarregada = $this->plataforma()->mesa($mesa->id);
        $this->assertSame($recarregada->participantesLocais()[0]->id, $recarregada->partidaAtiva()?->vezAtual());

        $consulta = $this->pdo->prepare("SELECT COUNT(*) FROM eventos_partida WHERE mesa_id = ? AND tipo = 'vez_pulada' AND origem = 'sistema'");
        $consulta->execute([$mesa->id]);
        $this->assertSame(1, (int) $consulta->fetchColumn());
    }

    #[Test]
    public function alteracaoSimultaneaDaMesmaMesaGeraConflito(): void
    {
        $mesa = $this->plataforma()->criarMesa('ana');
        $repositorio = new PdoRepositorioDeMesas($this->pdo, $this->relogio);
        $primeira = $repositorio->buscar($mesa->id);
        $segunda = $repositorio->buscar($mesa->id);

        $primeira?->adicionarMembro('bruno');
        $repositorio->salvar($primeira);

        $segunda?->adicionarMembro('carla');
        $this->expectException(ConflitoDeConcorrencia::class);
        $repositorio->salvar($segunda);
    }

    #[Test]
    public function oBancoGuardaAsVariasMesasAbertasDaPessoa(): void
    {
        $doBruno = $this->plataforma()->criarMesa('bruno');
        $mesaDaAna = $this->plataforma()->criarMesa('ana');
        $repositorio = new PdoRepositorioDeMesas($this->pdo, $this->relogio);
        $mesa = $repositorio->buscar($mesaDaAna->id);
        $mesa?->adicionarMembro('bruno');
        $repositorio->salvar($mesa);

        $ids = array_map(static fn ($m): string => $m->id, $repositorio->mesasAbertasDe('bruno'));
        $this->assertEqualsCanonicalizing([$doBruno->id, $mesaDaAna->id], $ids);
    }

    #[Test]
    public function amizadeConviteEBloqueioPersistem(): void
    {
        $mesa = $this->plataforma()->criarMesa('ana');
        $this->plataforma()->solicitarAmizade('ana', 'bruno');
        $this->assertSame(['ana'], $this->plataforma()->pedidosDeAmizadeRecebidos('bruno'));
        $this->plataforma()->aceitarAmizade('bruno', 'ana');
        $convite = $this->plataforma()->convidar($mesa->id, 'ana', 'bruno');
        $this->assertCount(1, $this->plataforma()->convitesPendentesPara('bruno'));

        $this->plataforma()->aceitarConvite($convite->id, 'bruno');

        $this->assertTrue($this->plataforma()->saoAmigos('bruno', 'ana'));
        $this->assertSame([], $this->plataforma()->convitesPendentesPara('bruno'));
        $this->assertSame($mesa->id, ($this->plataforma()->mesasAbertasDe('bruno')[0] ?? null)?->id);

        $this->plataforma()->solicitarAmizade('ana', 'carla');
        $this->plataforma()->aceitarAmizade('carla', 'ana');
        $paraCarla = $this->plataforma()->convidar($mesa->id, 'ana', 'carla');
        $this->plataforma()->bloquear('carla', 'ana');
        $this->assertSame('cancelado', $this->plataforma()->convite($paraCarla->id)->situacao->value);
    }

    #[Test]
    public function participacaoLocalPodeSerReconhecidaPeloDonoDoEmail(): void
    {
        $mesa = $this->plataforma()->criarMesa('ana');
        $this->plataforma()->naMesa($mesa->id, static fn (Mesa $m) => $m->cadastrarParticipanteLocal('Bruno', 'Bruno@Exemplo.com', 'ana'));

        $pendentes = $this->plataforma()->participacoesLocaisPendentes('bruno@exemplo.com');
        $this->assertCount(1, $pendentes);

        $this->plataforma()->reconhecerParticipacaoLocal($pendentes[0]->id, 'bruno', 'bruno@exemplo.com');

        $this->assertSame([$pendentes[0]->id], $this->plataforma()->participacoesReconhecidasDe('bruno'));
        $this->assertSame([], $this->plataforma()->participacoesLocaisPendentes('bruno@exemplo.com'));
    }

    #[Test]
    public function depoisDeEncerrarOutroMembroCriaMesaNova(): void
    {
        $mesa = $this->plataforma()->criarMesa('ana', 'Grupo da Ana');
        $this->plataforma()->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro('bruno'));

        $this->plataforma()->encerrarMesa($mesa->id, 'ana');

        $consulta = $this->pdo->prepare('SELECT usuario_ativo FROM membros_mesa WHERE mesa_id = ?');
        $consulta->execute([$mesa->id]);
        $this->assertSame([null, null], $consulta->fetchAll(PDO::FETCH_COLUMN));
        $this->assertNull(($this->plataforma()->mesasAbertasDe('bruno')[0] ?? null));

        $outra = $this->plataforma()->criarMesa('bruno', 'Mesa do Bruno');
        $this->assertSame($outra->id, ($this->plataforma()->mesasAbertasDe('bruno')[0] ?? null)?->id);
    }

    /** Uma plataforma nova a cada chamada, como requisições diferentes; todas na mesma transação de teste. */
    #[Test]
    public function conviteDeAmizadeAceitoApareceNaListaDosDois(): void
    {
        $this->plataforma()->aceitarConviteDeAmizade('ana', 'bruno');
        $this->plataforma()->aceitarConviteDeAmizade('carla', 'ana');

        $amigosDaAna = $this->plataforma()->amigosDe('ana');
        sort($amigosDaAna);
        $this->assertSame(['bruno', 'carla'], $amigosDaAna);
        $this->assertSame(['ana'], $this->plataforma()->amigosDe('bruno'));
        $this->assertSame([], $this->plataforma()->pedidosDeAmizadeRecebidos('bruno'));
    }

    private function plataforma(): Plataforma
    {
        return new Plataforma(
            $this->relogio,
            new PdoRepositorioDeMesas($this->pdo, $this->relogio),
            new PdoRepositorioDeConvites($this->pdo, $this->relogio),
            new PdoRepositorioSocial($this->pdo, $this->relogio),
            new TransacaoDoLaravel(),
            new JogosSempreDisponiveis(),
        );
    }
}
