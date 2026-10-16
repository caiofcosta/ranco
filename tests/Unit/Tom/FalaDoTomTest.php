<?php

declare(strict_types=1);

namespace Tests\Unit\Tom;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\Partida;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocacao;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;
use App\Infra\Memoria\PlataformaEmMemoria;
use App\Infra\Memoria\RepositorioDeProvocacoesEmMemoria;
use App\Models\User;
use App\Tom\FalaDoTom;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class FalaDoTomTest extends TestCase
{
    use RefreshDatabase;

    private Plataforma $plataforma;

    private User $ana;

    private User $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-01 10:00:00')));
        $this->app->instance(Plataforma::class, $this->plataforma);
        $this->app->instance(RepositorioDeProvocacoes::class, new RepositorioDeProvocacoesEmMemoria(
            [
                new Provocacao(1, 'Sua vez.', 2, ['vez_pendente']),
                new Provocacao(2, 'Convite de {admin}.', 2, ['convite_pendente']),
                new Provocacao(3, 'Partida pronta.', 2, ['partida_pronta']),
                new Provocacao(4, 'Vitória sua.', 1, ['vitoria_recente']),
                new Provocacao(5, 'Derrota para {vencedor}.', 1, ['derrota_recente']),
                new Provocacao(6, 'Mesa sozinha.', 1, ['mesa_aguardando']),
                new Provocacao(7, 'Sem mesa.', 1, ['vazio']),
                new Provocacao(8, 'Sem novidade.', 1, ['sem_novidade']),
            ],
            [
                Momento::VezPendente->value => ['vez_pendente'],
                Momento::ConvitePendente->value => ['convite_pendente'],
                Momento::PartidaPronta->value => ['partida_pronta'],
                Momento::VitoriaRecente->value => ['vitoria_recente'],
                Momento::DerrotaRecente->value => ['derrota_recente'],
                Momento::MesaAguardando->value => ['mesa_aguardando'],
                Momento::SemMesa->value => ['vazio'],
                Momento::SemNovidade->value => ['sem_novidade'],
            ],
        ));

        $this->ana = User::factory()->create(['name' => 'Ana']);
        $this->bruno = User::factory()->create(['name' => 'Bruno']);
    }

    #[Test]
    public function urgenteGanhaDaComum(): void
    {
        $this->mesaSozinha($this->ana);
        $this->convidar($this->bruno, $this->ana);

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Convite de Bruno.', $fala?->texto);
        $this->assertSame('observando', $fala?->pose);
        $this->assertSame('atento', $fala?->expressao);
    }

    #[Test]
    public function entreUrgentesAVezGanhaDoConvite(): void
    {
        $this->partidaNaVezDe($this->ana, $this->bruno);
        $carla = User::factory()->create(['name' => 'Carla']);
        $this->convidar($carla, $this->ana);

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Sua vez.', $fala?->texto);
    }

    #[Test]
    public function entreUrgentesOConviteGanhaDaPartidaPronta(): void
    {
        $this->partidaPronta($this->ana);
        $this->convidar($this->bruno, $this->ana);

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Convite de Bruno.', $fala?->texto);
    }

    #[Test]
    public function semSituacaoVerdadeiraCaiEmSemNovidade(): void
    {
        $this->mesaCom($this->ana, $this->bruno);

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Sem novidade.', $fala?->texto);
        $this->assertSame('base', $fala?->pose);
        $this->assertSame('neutro', $fala?->expressao);
    }

    #[Test]
    public function aComumSeMantemEntreCarregamentos(): void
    {
        $falas = $this->falas();
        $primeira = $falas->para($this->id($this->ana));
        session()->put(FalaDoTom::SESSAO, [
            'momento' => Momento::SemMesa->value,
            'chave' => 'sem_mesa',
            'texto' => 'ANTIGA',
        ]);

        $segunda = $falas->para($this->id($this->ana));

        $this->assertSame('Sem mesa.', $primeira?->texto);
        $this->assertSame('ANTIGA', $segunda?->texto);
    }

    #[Test]
    public function aComumTrocaQuandoSurgeUmaUrgente(): void
    {
        $falas = $this->falas();
        $falas->para($this->id($this->ana));
        session()->put(FalaDoTom::SESSAO, [
            'momento' => Momento::SemMesa->value,
            'chave' => 'sem_mesa',
            'texto' => 'ANTIGA',
        ]);
        $this->convidar($this->bruno, $this->ana);

        $urgente = $falas->para($this->id($this->ana));

        $this->assertSame('Convite de Bruno.', $urgente?->texto);
        $this->assertNull(session(FalaDoTom::SESSAO));

        $this->recusarConviteDe($this->ana);
        $depois = $falas->para($this->id($this->ana));

        $this->assertSame('Sem mesa.', $depois?->texto);
    }

    #[Test]
    public function aComumTrocaQuandoOFatoDeixaDeSerVerdade(): void
    {
        $falas = $this->falas();
        $falas->para($this->id($this->ana));
        session()->put(FalaDoTom::SESSAO, [
            'momento' => Momento::SemMesa->value,
            'chave' => 'sem_mesa',
            'texto' => 'ANTIGA',
        ]);
        $this->mesaSozinha($this->ana);

        $fala = $falas->para($this->id($this->ana));

        $this->assertSame('Mesa sozinha.', $fala?->texto);
    }

    #[Test]
    public function aUrgenteSomeQuandoOFatoAcaba(): void
    {
        $this->convidar($this->bruno, $this->ana);
        $falas = $this->falas();

        $this->assertSame('Convite de Bruno.', $falas->para($this->id($this->ana))?->texto);

        $this->recusarConviteDe($this->ana);

        $fala = $falas->para($this->id($this->ana));

        $this->assertSame('Sem mesa.', $fala?->texto);
        $this->assertNotSame('Convite de Bruno.', $fala?->texto);
    }

    #[Test]
    public function vitoriaRecenteUsaONomeDeQuemVenceu(): void
    {
        $this->encerrarPartida($this->ana, $this->bruno, 10, 1);

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Vitória sua.', $fala?->texto);
        $this->assertStringNotContainsString($this->id($this->ana), (string) $fala?->texto);
    }

    #[Test]
    public function derrotaRecenteUsaONomeDeQuemVenceu(): void
    {
        $this->encerrarPartida($this->ana, $this->bruno, 1, 10);

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Derrota para Bruno.', $fala?->texto);
    }

    #[Test]
    public function empateNoPrimeiroMostraVitoriaParaOsDoisEDerrotaSemFrase(): void
    {
        $carla = User::factory()->create(['name' => 'Carla']);
        $mesa = $this->mesaCom($this->ana, $this->bruno);
        $this->tornarAmigos($this->ana, $carla);
        $convite = $this->plataforma->convidar($mesa->id, $this->id($this->ana), $this->id($carla));
        $this->plataforma->aceitarConvite($convite->id, $this->id($carla));
        $this->plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($carla): void {
            $partida = $mesa->novaPartida(new ConfiguracaoPartida(2, 6, 300, limiteRodadas: 1), $this->id($this->ana));
            $partida->iniciar($this->id($this->ana));
            $this->jogar($partida, $this->id($this->ana), 10);
            $this->jogar($partida, $this->id($this->bruno), 10);
            $this->jogar($partida, $this->id($carla), 1);
        });

        $this->assertSame('Vitória sua.', $this->falas()->para($this->id($this->ana))?->texto);
        $this->assertSame('Vitória sua.', $this->falas()->para($this->id($this->bruno))?->texto);
        $this->assertNull($this->falas()->para($this->id($carla)), 'Ouro dividido não tem frase de derrota; a fala fica vazia, sem erro.');
    }

    #[Test]
    public function quemSaiuNaFrenteEOuvidoComoVencedor(): void
    {
        $carla = User::factory()->create(['name' => 'Carla']);
        $mesa = $this->mesaCom($this->ana, $this->bruno);
        $this->tornarAmigos($this->ana, $carla);
        $convite = $this->plataforma->convidar($mesa->id, $this->id($this->ana), $this->id($carla));
        $this->plataforma->aceitarConvite($convite->id, $this->id($carla));
        $this->plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($carla): void {
            $partida = $mesa->novaPartida(new ConfiguracaoPartida(2, 6, 300, limiteRodadas: 1), $this->id($this->ana));
            $partida->iniciar($this->id($this->ana));
            $this->jogar($partida, $this->id($this->ana), 5);
            $this->jogar($partida, $this->id($this->bruno), 10);
            $partida->abandonar($this->id($this->bruno));
            $this->jogar($partida, $this->id($carla), 1);
        });

        $this->assertSame('Vitória sua.', $this->falas()->para($this->id($this->bruno))?->texto);
        $this->assertSame('Derrota para Bruno.', $this->falas()->para($this->id($this->ana))?->texto);
        $this->assertSame('Derrota para Bruno.', $this->falas()->para($this->id($carla))?->texto);
    }

    #[Test]
    public function vencedorLocalEntraComONomeDaMesa(): void
    {
        $ana = $this->id($this->ana);
        $mesa = $this->plataforma->criarMesa($ana);
        $this->plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($ana): void {
            $local = $mesa->cadastrarParticipanteLocal('Zeca', 'zeca@exemplo.com', $ana);
            $partida = $mesa->novaPartida(new ConfiguracaoPartida(2, 6, 300, limiteRodadas: 1), $ana);
            $partida->iniciar($ana);
            $this->jogar($partida, $ana, 1);
            $this->jogar($partida, $local, 10);
        });

        $fala = $this->falas()->para($ana);

        $this->assertSame('Derrota para Zeca.', $fala?->texto);
    }

    #[Test]
    public function conviteSemNomeDeUsuarioEDescartado(): void
    {
        $mesa = $this->plataforma->criarMesa('fantasma');
        $this->plataforma->solicitarAmizade('fantasma', $this->id($this->ana));
        $this->plataforma->aceitarAmizade($this->id($this->ana), 'fantasma');
        $this->plataforma->convidar($mesa->id, 'fantasma', $this->id($this->ana));

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Sem mesa.', $fala?->texto);
    }

    #[Test]
    public function partidaPausadaNaoContaComoVez(): void
    {
        $mesa = $this->partidaNaVezDe($this->ana, $this->bruno);
        $this->plataforma->naMesa($mesa->id, fn (Mesa $mesa) => $mesa->partidaAtiva()?->pausar($this->id($this->ana)));

        $fala = $this->falas()->para($this->id($this->ana));

        $this->assertSame('Sem novidade.', $fala?->texto);
    }

    private function falas(): FalaDoTom
    {
        return $this->app->make(FalaDoTom::class);
    }

    private function id(User $usuario): string
    {
        return $usuario->identificadorNoDominio();
    }

    private function mesaSozinha(User $admin): Mesa
    {
        return $this->plataforma->criarMesa($this->id($admin));
    }

    private function mesaCom(User $admin, User $outro): Mesa
    {
        $mesa = $this->mesaSozinha($admin);
        $this->tornarAmigos($admin, $outro);
        $convite = $this->plataforma->convidar($mesa->id, $this->id($admin), $this->id($outro));
        $this->plataforma->aceitarConvite($convite->id, $this->id($outro));

        return $mesa;
    }

    /** Quem convida já está na própria mesa; quem recebe pode estar em outra. */
    private function convidar(User $de, User $para): void
    {
        $mesa = ($this->plataforma->mesasAbertasDe($this->id($de))[0] ?? null) ?? $this->plataforma->criarMesa($this->id($de));
        $this->tornarAmigos($de, $para);
        $this->plataforma->convidar($mesa->id, $this->id($de), $this->id($para));
    }

    private function tornarAmigos(User $de, User $para): void
    {
        if ($this->plataforma->saoAmigos($this->id($de), $this->id($para))) {
            return;
        }

        $this->plataforma->solicitarAmizade($this->id($de), $this->id($para));
        $this->plataforma->aceitarAmizade($this->id($para), $this->id($de));
    }

    private function recusarConviteDe(User $quem): void
    {
        $convite = $this->plataforma->convitesPendentesPara($this->id($quem))[0];
        $this->plataforma->recusarConvite($convite->id, $this->id($quem));
    }

    private function partidaNaVezDe(User $admin, User $outro): Mesa
    {
        $mesa = $this->mesaCom($admin, $outro);
        $this->plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($admin): void {
            $mesa->novaPartida(new ConfiguracaoPartida(2, 6, 300), $this->id($admin))->iniciar($this->id($admin));
        });

        return ($this->plataforma->mesasAbertasDe($this->id($admin))[0] ?? null) ?? throw new \RuntimeException('Mesa ausente.');
    }

    private function partidaPronta(User $admin): void
    {
        $mesa = $this->mesaSozinha($admin);
        $this->plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($admin): void {
            $mesa->novaPartida(new ConfiguracaoPartida(1, 6, 300), $this->id($admin));
        });
    }

    private function encerrarPartida(User $admin, User $outro, int $pontosAdmin, int $pontosOutro): void
    {
        $mesa = $this->mesaCom($admin, $outro);
        $this->plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($admin, $outro, $pontosAdmin, $pontosOutro): void {
            $partida = $mesa->novaPartida(new ConfiguracaoPartida(2, 6, 300, limiteRodadas: 1), $this->id($admin));
            $partida->iniciar($this->id($admin));
            $this->jogar($partida, $this->id($admin), $pontosAdmin);
            $this->jogar($partida, $this->id($outro), $pontosOutro);
        });
    }

    private function jogar(Partida $partida, string $jogador, int $resultado): void
    {
        $partida->iniciarTentativa($jogador);
        $partida->concluirTentativa($jogador, $resultado);
    }
}
