<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Conquista\Medalhas;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoParticipacao;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use Illuminate\Support\Facades\Route;

/** O que a tela da mesa mostra da partida: estado, placar, vez e o que o admin pode fazer. */
final readonly class PartidaNaMesa
{
    /**
     * @param  list<string>  $participantes
     * @param  list<array{id: string, nome: string, pontos: int, situacao: string}>  $placar
     * @param  list<array{id: string, nome: string}>  $admitiveis
     * @param  list<string>  $faltamJogar  No paralelo, quem ainda não concluiu a rodada.
     * @param  list<array{valor: string, rotulo: string}>  $modos  Modos que o jogo aceita, para a criação.
     * @param  list<array{rodada: int, ganhadores: list<array{nome: string, primeiroNome: string, pontos: int}>, participantes: list<array{nome: string, primeiroNome: string, tempo: ?string}>}>  $historicoLabirinto  Rodadas encerradas do Labirinto, da mais recente para a mais antiga.
     */
    public function __construct(
        public string $mesaId,
        public bool $ehAdmin,
        public string $estado,
        public int $minimoJogadores,
        public int $prazoPadraoSegundos,
        public bool $podeIniciar,
        public bool $podeJogarAVez,
        public bool $vezDeParticipanteLocal,
        public bool $podePularVez,
        public bool $pausada,
        public ?int $rodada,
        public ?string $vezId,
        public ?string $vezNome,
        public ?string $prazoExpiraEm,
        public ?string $vencedorNome,
        public array $participantes,
        public array $placar,
        public array $admitiveis,
        public ?string $urlJogar,
        public ?string $fraseDaMedalha,
        public bool $variosVencedores = false,
        public bool $paralelo = false,
        public array $faltamJogar = [],
        public array $modos = [],
        public bool $jogando = false,
        public ?string $urlAssistir = null,
        public bool $bloqueadaPorParticipantesLocais = false,
        public ?int $metaPontos = null,
        public ?int $limiteRodadas = null,
        public bool $ehLabirinto = false,
        public array $historicoLabirinto = [],
    ) {}

    public static function de(Mesa $mesa, string $usuario, ?Partida $partida = null): self
    {
        PessoasDaMesa::preparar($mesa);
        $partida ??= self::partidaDaTela($mesa);
        $padrao = CatalogoDeJogos::configuracao($partida?->configuracao()->jogo ?? CatalogoDeJogos::LABIRINTO, null, null, null);
        $ehAdmin = $mesa->admin() === $usuario;
        $estado = self::estado($partida);
        $paralelo = $partida?->modo() === ModoExecucao::Paralelo;
        $vezId = $partida?->vezAtual();
        $vezDeParticipanteLocal = $vezId !== null && $mesa->participanteLocal($vezId) !== null;
        $podeJogarAVez = $estado === 'andamento' && ! $partida->pausada()
            && $mesa->porQuemJoga($usuario) !== null;
        $tentativaAberta = $partida !== null && $vezId !== null
            && $partida->tentativaDe($partida->rodadaAtual(), $vezId, $partida->desempateAtual()) !== null;

        $jogando = $partida?->participanteJogando();

        return new self(
            $mesa->id,
            $ehAdmin,
            $estado,
            $padrao->minimoJogadores,
            $padrao->prazoVezSegundos,
            $mesa->podeIniciarPartida(),
            $podeJogarAVez,
            $vezDeParticipanteLocal,
            $ehAdmin && $estado === 'andamento' && $vezId !== null && ! $tentativaAberta,
            $partida?->pausada() ?? false,
            $estado === 'andamento' ? $partida?->rodadaAtual() : null,
            $vezId,
            $vezId === null ? null : PessoasDaMesa::nome($mesa, $vezId),
            self::prazo($partida),
            self::nomesDosVencedores($mesa, $partida, $estado),
            $partida === null ? [] : array_values(array_filter(array_map(
                static fn (string $id): ?string => PessoasDaMesa::nome($mesa, $id),
                $partida->ordem(),
            ))),
            self::placar($mesa, $partida),
            self::admitiveis($mesa, $partida, $estado),
            $podeJogarAVez ? self::urlJogar($mesa->id, $partida, self::numeroDa($mesa, $partida)) : null,
            self::fraseDaMedalha($mesa, $partida, $estado),
            $estado === 'encerrada' && count($partida?->vencedores() ?? []) > 1,
            $paralelo,
            $paralelo && $estado === 'andamento' ? array_values(array_filter(array_map(
                static fn (string $id): ?string => PessoasDaMesa::nome($mesa, $id),
                $partida->pendentesDaRodada(),
            ))) : [],
            array_map(static fn (ModoExecucao $modo): array => [
                'valor' => $modo->value,
                'rotulo' => self::rotuloModo($modo),
            ], CatalogoDeJogos::modos($partida?->configuracao()->jogo ?? CatalogoDeJogos::LABIRINTO)),
            $jogando !== null,
            $jogando !== null && CatalogoDeJogos::transmiteAoVivo($partida->configuracao()->jogo)
                && ! $mesa->podeJogarPor($usuario, $jogando)
                ? route('mesas.assistir', $mesa->id) : null,
            false,
            $partida?->configuracao()->metaPontos,
            $partida?->configuracao()->limiteRodadas,
            $partida?->configuracao()->jogo === CatalogoDeJogos::LABIRINTO,
            self::historicoLabirinto($mesa, $partida),
        );
    }

    /**
     * Rodadas já encerradas do Labirinto, da mais recente para a mais antiga, com o tempo de
     * cada participante e quem recebeu o ponto. Usa a tentativa base (sem desempate) de cada rodada.
     *
     * @return list<array{rodada: int, ganhadores: list<array{nome: string, primeiroNome: string, pontos: int}>, participantes: list<array{nome: string, primeiroNome: string, tempo: ?string}>}>
     */
    private static function historicoLabirinto(Mesa $mesa, ?Partida $partida): array
    {
        if ($partida === null
            || $partida->configuracao()->jogo !== CatalogoDeJogos::LABIRINTO
            || $partida->situacao() === SituacaoPartida::EmConfiguracao) {
            return [];
        }

        $ultimaConcluida = $partida->entreRodadas() ? $partida->rodadaAtual() : $partida->rodadaAtual() - 1;
        $rodadas = [];
        for ($r = $ultimaConcluida; $r >= 1; $r--) {
            $maxPontos = 0;
            foreach ($partida->participantesDaRodada($r) as $id) {
                $maxPontos = max($maxPontos, $partida->pontosNaRodada($r, $id));
            }

            $participantes = [];
            $ganhadores = [];
            foreach ($partida->participantesDaRodada($r) as $id) {
                $nome = PessoasDaMesa::nome($mesa, $id);
                if ($nome === null) {
                    continue;
                }
                $tentativa = $partida->tentativaDe($r, $id, 0);
                $participantes[] = [
                    'nome' => $nome,
                    'primeiroNome' => self::primeiroNome($nome),
                    'tempo' => $tentativa?->situacao === SituacaoTentativa::Concluida
                        ? number_format($tentativa->resultado / 1000, 3, ',', '.').' s'
                        : null,
                    'posicao' => $partida->posicaoNaRodada($r, $id) ?? PHP_INT_MAX,
                ];
                $pontos = $partida->pontosNaRodada($r, $id);
                if ($maxPontos > 0 && $pontos === $maxPontos) {
                    $ganhadores[] = ['nome' => $nome, 'primeiroNome' => self::primeiroNome($nome), 'pontos' => $pontos];
                }
            }

            usort($participantes, static fn (array $a, array $b): int => $a['posicao'] <=> $b['posicao']);
            $rodadas[] = [
                'rodada' => $r,
                'ganhadores' => $ganhadores,
                'participantes' => array_map(
                    static fn (array $linha): array => ['nome' => $linha['nome'], 'primeiroNome' => $linha['primeiroNome'], 'tempo' => $linha['tempo']],
                    $participantes,
                ),
            ];
        }

        return $rodadas;
    }

    private static function primeiroNome(string $nome): string
    {
        return (preg_split('/\s+/u', trim($nome)) ?: [$nome])[0];
    }

    public static function rotuloModo(ModoExecucao $modo): string
    {
        return match ($modo) {
            ModoExecucao::Sequencial => 'Um por vez',
            ModoExecucao::Paralelo => 'Todos na mesma rodada',
        };
    }

    public function semPartidaAtiva(): bool
    {
        return $this->estado === 'sem' || $this->estado === 'encerrada';
    }

    private static function partidaDaTela(Mesa $mesa): ?Partida
    {
        $ativa = $mesa->partidaAtiva();
        if ($ativa !== null) {
            return $ativa;
        }

        $todas = $mesa->partidas();

        return $todas === [] ? null : $todas[array_key_last($todas)];
    }

    private static function estado(?Partida $partida): string
    {
        if ($partida === null) {
            return 'sem';
        }

        return match ($partida->situacao()) {
            SituacaoPartida::EmConfiguracao => 'configuracao',
            SituacaoPartida::EmAndamento => 'andamento',
            SituacaoPartida::Encerrada => 'encerrada',
        };
    }

    private static function prazo(?Partida $partida): ?string
    {
        $quando = $partida?->prazoExpiraEm();
        if ($quando === null) {
            return null;
        }

        return $quando->format('H:i');
    }

    /**
     * @return list<array{id: string, nome: string, pontos: int, situacao: string}>
     */
    private static function placar(Mesa $mesa, ?Partida $partida): array
    {
        if ($partida === null || $partida->situacao() === SituacaoPartida::EmConfiguracao) {
            return [];
        }

        $linhas = [];
        foreach ($partida->placar() as $linha) {
            $nome = PessoasDaMesa::nome($mesa, $linha['participante']);
            if ($nome === null) {
                continue;
            }
            $linhas[] = [
                'id' => $linha['participante'],
                'nome' => $nome,
                'pontos' => $linha['pontos'],
                'situacao' => self::rotuloSituacao($linha['situacao']),
            ];
        }

        return $linhas;
    }

    /**
     * @return list<array{id: string, nome: string}>
     */
    private static function admitiveis(Mesa $mesa, ?Partida $partida, string $estado): array
    {
        if ($partida === null || ($estado !== 'configuracao' && $estado !== 'andamento')) {
            return [];
        }

        $semLocais = $partida->modo() === ModoExecucao::Paralelo;
        $lista = [];
        foreach ($mesa->membrosAtivos() as $id) {
            if ($partida->estaNaDisputa($id) || ($semLocais && $mesa->participanteLocal($id) !== null)) {
                continue;
            }
            $nome = PessoasDaMesa::nome($mesa, $id);
            if ($nome === null) {
                continue;
            }
            $lista[] = ['id' => $id, 'nome' => $nome];
        }

        return $lista;
    }

    private static function rotuloSituacao(SituacaoParticipacao $situacao): string
    {
        return match ($situacao) {
            SituacaoParticipacao::Ativa => 'Na disputa',
            SituacaoParticipacao::Abandonada => 'Saiu',
            SituacaoParticipacao::Removida => 'Removido',
            SituacaoParticipacao::Concluida => 'Terminou',
        };
    }

    private static function fraseDaMedalha(Mesa $mesa, ?Partida $partida, string $estado): ?string
    {
        if ($estado !== 'encerrada' || $partida === null || $partida->vencedores() === []) {
            return null;
        }

        $numero = self::numeroDa($mesa, $partida);

        return $numero === null ? null : Medalhas::frase($mesa->id, $numero);
    }

    private static function numeroDa(Mesa $mesa, Partida $partida): ?int
    {
        foreach ($mesa->partidas() as $indice => $candidata) {
            if ($candidata === $partida) {
                return $indice + 1;
            }
        }

        return null;
    }

    private static function nomesDosVencedores(Mesa $mesa, ?Partida $partida, string $estado): ?string
    {
        if ($estado !== 'encerrada' || $partida === null) {
            return null;
        }

        $nomes = [];
        foreach ($partida->vencedores() as $id) {
            $nome = PessoasDaMesa::nome($mesa, $id);
            if ($nome !== null) {
                $nomes[] = $nome;
            }
        }

        return $nomes === [] ? null : PessoasDaMesa::juntar($nomes);
    }

    private static function urlJogar(string $mesaId, ?Partida $partida, ?int $numero): string
    {
        return Route::has('mesas.jogar')
            ? route('mesas.jogar', $mesaId)
            : url('/mesas/'.$mesaId.'/jogar');
    }
}
