<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Plataforma;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\ParticipanteLocal;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Plataforma\RepositorioDeMesas;
use App\Dominio\Plataforma\RepositorioDeVisibilidade;
use App\Infra\Memoria\RepositorioDeConvitesEmMemoria;
use App\Infra\Memoria\RepositorioDeMesasEmMemoria;
use App\Infra\Memoria\RepositorioDeVisibilidadeEmMemoria;
use App\Infra\Memoria\RepositorioSocialEmMemoria;
use App\Infra\Memoria\TransacaoEmMemoria;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;
use Tests\Support\JogosSempreDisponiveis;

/**
 * No REPEATABLE READ do MariaDB, a primeira leitura comum da transação fixa a foto do banco.
 * Por isso a pessoa precisa ser travada antes de qualquer leitura que decide limite ou visibilidade.
 */
final class TravaAntesDeLerTest extends TestCase
{
    /** @var list<string> */
    private array $registro = [];

    private Plataforma $plataforma;

    protected function setUp(): void
    {
        $registro = &$this->registro;
        $mesas = new class(new RepositorioDeMesasEmMemoria(), $registro) implements RepositorioDeMesas
        {
            /** @param list<string> $registro */
            public function __construct(private RepositorioDeMesasEmMemoria $real, private array &$registro) {}

            public function novoId(): string { return $this->real->novoId(); }

            public function buscar(string $id): ?Mesa { $this->registro[] = 'ler'; return $this->real->buscar($id); }

            public function salvar(Mesa $mesa): void { $this->real->salvar($mesa); }

            public function mesasAbertasDe(string $usuario): array { $this->registro[] = 'ler'; return $this->real->mesasAbertasDe($usuario); }

            public function travarPessoa(string $usuario): void { $this->registro[] = 'travar'; }

            public function idsComPartidaEmAndamento(): array { return $this->real->idsComPartidaEmAndamento(); }

            /** @return list<ParticipanteLocal> */
            public function participantesLocaisDoEmail(string $email): array { return $this->real->participantesLocaisDoEmail($email); }

            /** @return list<ParticipanteLocal> */
            public function participantesLocaisReconhecidosPor(string $usuario): array { return $this->real->participantesLocaisReconhecidosPor($usuario); }
        };
        $visibilidade = new class(new RepositorioDeVisibilidadeEmMemoria(), $registro) implements RepositorioDeVisibilidade
        {
            /** @param list<string> $registro */
            public function __construct(private RepositorioDeVisibilidadeEmMemoria $real, private array &$registro) {}

            public function invisivel(string $pessoa): bool { $this->registro[] = 'ler'; return $this->real->invisivel($pessoa); }

            public function definir(string $pessoa, bool $invisivel): void { $this->real->definir($pessoa, $invisivel); }
        };

        $this->plataforma = new Plataforma(
            new RelogioFalso(new DateTimeImmutable('2026-10-05 10:00:00')),
            $mesas,
            new RepositorioDeConvitesEmMemoria(),
            new RepositorioSocialEmMemoria(),
            new TransacaoEmMemoria(),
            new JogosSempreDisponiveis(),
            visibilidade: $visibilidade,
        );
    }

    #[Test]
    public function criarMesaTravaAPessoaAntesDeLer(): void
    {
        $this->registro = [];
        $this->plataforma->criarMesa('ana');

        $this->assertSame('travar', $this->registro[0] ?? null);
    }

    #[Test]
    public function aceitarConviteTravaAPessoaAntesDeLer(): void
    {
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');
        $convite = $this->plataforma->convidar($this->plataforma->criarMesa('ana')->id, 'ana', 'bruno');

        $this->registro = [];
        $this->plataforma->aceitarConvite($convite->id, 'bruno');

        $this->assertSame('travar', $this->registro[0] ?? null);
    }

    #[Test]
    public function iniciarJogadaETornarInvisivelTravamAPessoaAntesDeLer(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');
        $this->plataforma->aceitarConvite($this->plataforma->convidar($mesa->id, 'ana', 'bruno')->id, 'bruno');
        $this->plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo), 'ana');
        $this->plataforma->iniciarPartida($mesa->id, 'ana');

        $this->registro = [];
        $this->plataforma->iniciarTentativa($mesa->id, 'bruno', static fn (Mesa $m): array => [$m->partidaAtiva(), 'bruno']);
        $this->assertSame('travar', $this->registro[0] ?? null);

        $this->registro = [];
        $this->plataforma->ficarInvisivel('ana');
        $this->assertSame('travar', $this->registro[0] ?? null);
    }
}
