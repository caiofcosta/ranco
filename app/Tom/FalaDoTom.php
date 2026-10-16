<?php

declare(strict_types=1);

namespace App\Tom;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocador;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Random\Randomizer;

/**
 * Escolhe a fala do Tom no Início: situações verdadeiras da pessoa, urgentes na frente,
 * comum guardada na sessão. Sem frase utilizável, devolve null e a tela segue sem o Tom.
 */
final class FalaDoTom
{
    public const SESSAO = 'tom.fala_do_inicio';

    /** @var array<string, int> */
    private const ORDEM_URGENTE = [
        'inicio.vez_pendente' => 0,
        'inicio.convite_pendente' => 1,
        'inicio.partida_pronta' => 2,
    ];

    private readonly Randomizer $sorteio;

    public function __construct(
        private readonly Plataforma $plataforma,
        private readonly Provocador $provocador,
        private readonly Session $sessao,
        ?Randomizer $sorteio = null,
    ) {
        $this->sorteio = $sorteio ?? new Randomizer();
    }

    public function para(string $usuario): ?Fala
    {
        $situacoes = $this->situacoesDe($usuario);
        $urgentes = array_values(array_filter($situacoes, static fn (array $s): bool => $s['urgente']));
        if ($urgentes !== []) {
            usort(
                $urgentes,
                static fn (array $a, array $b): int => self::ORDEM_URGENTE[$a['momento']->value] <=> self::ORDEM_URGENTE[$b['momento']->value],
            );
            $this->sessao->forget(self::SESSAO);

            return $this->falar($urgentes[0]);
        }

        $comuns = array_values(array_filter($situacoes, static fn (array $s): bool => !$s['urgente']));
        if ($comuns === []) {
            $comuns[] = $this->situacao(Momento::SemNovidade, [], false, 'sem_novidade');
        }

        $guardada = $this->comumAindaVerdadeira($comuns);
        if ($guardada !== null) {
            return new Fala($guardada, 'base', 'neutro');
        }

        $escolhida = $comuns[$this->sorteio->getInt(0, count($comuns) - 1)];
        $fala = $this->falar($escolhida);
        if ($fala !== null) {
            $this->sessao->put(self::SESSAO, [
                'momento' => $escolhida['momento']->value,
                'chave' => $escolhida['chave'],
                'texto' => $fala->texto,
            ]);
        }

        return $fala;
    }

    /**
     * @return list<array{momento: Momento, variaveis: array<string, string>, urgente: bool, chave: string}>
     */
    private function situacoesDe(string $usuario): array
    {
        $mesas = $this->plataforma->mesasAbertasDe($usuario);
        $situacoes = [];

        if ($mesas === []) {
            $situacoes[] = $this->situacao(Momento::SemMesa, [], false, 'sem_mesa');
        }
        foreach ($mesas as $mesa) {
            $partida = $mesa->partidaAtiva();
            if ($partida !== null
                && $partida->situacao() === SituacaoPartida::EmAndamento
                && !$partida->pausada()
                && $partida->vezAtual() === $usuario
            ) {
                $situacoes[] = $this->situacao(Momento::VezPendente, [], true, "mesa:{$mesa->id}:vez:{$usuario}");
            }

            if ($usuario === $mesa->admin()
                && $partida !== null
                && $mesa->podeIniciarPartida()
            ) {
                $situacoes[] = $this->situacao(Momento::PartidaPronta, [], true, "mesa:{$mesa->id}:pronta");
            }

            $resultado = $this->resultadoRecente($mesa, $usuario);
            if ($resultado !== null) {
                $situacoes[] = $resultado;
            }

            if (count($mesa->membrosAtivos()) === 1) {
                $situacoes[] = $this->situacao(Momento::MesaAguardando, [], false, "mesa:{$mesa->id}:sozinho");
            }
        }

        $convite = $this->convitePendente($usuario);
        if ($convite !== null) {
            $situacoes[] = $convite;
        }

        return $situacoes;
    }

    /**
     * @return array{momento: Momento, variaveis: array<string, string>, urgente: bool, chave: string}|null
     */
    private function resultadoRecente(Mesa $mesa, string $usuario): ?array
    {
        $partidas = $mesa->partidas();
        if ($partidas === []) {
            return null;
        }

        $ultima = $partidas[array_key_last($partidas)];
        if ($ultima->situacao() !== SituacaoPartida::Encerrada) {
            return null;
        }

        $vencedores = $ultima->vencedores();
        if ($vencedores === [] || !$this->participou($ultima->placar(), $usuario)) {
            return null;
        }

        $variaveis = [];
        if (count($vencedores) === 1) {
            $nome = $this->nomeDe($vencedores[0], $mesa);
            if ($nome === null) {
                return null;
            }
            $variaveis['vencedor'] = $nome;
        }

        $momento = in_array($usuario, $vencedores, true) ? Momento::VitoriaRecente : Momento::DerrotaRecente;

        return $this->situacao(
            $momento,
            $variaveis,
            false,
            "mesa:{$mesa->id}:partidas:" . count($partidas) . ':vencedores:' . implode(',', $vencedores),
        );
    }

    /**
     * @param list<array{participante: string, pontos: int, situacao: mixed}> $placar
     */
    private function participou(array $placar, string $usuario): bool
    {
        foreach ($placar as $linha) {
            if ($linha['participante'] === $usuario) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{momento: Momento, variaveis: array<string, string>, urgente: bool, chave: string}|null
     */
    private function convitePendente(string $usuario): ?array
    {
        $convites = $this->plataforma->convitesPendentesPara($usuario);
        if ($convites === []) {
            return null;
        }

        $convite = $convites[0];
        $nome = $this->nomeDe($convite->de, null);
        if ($nome === null) {
            return null;
        }

        return $this->situacao(
            Momento::ConvitePendente,
            ['admin' => $nome],
            true,
            "convite:{$convite->id}",
        );
    }

    /** Nome de exibição: users.name, ou o nome do participante local. Sem nome, a situação não entra. */
    private function nomeDe(string $identificador, ?Mesa $mesa): ?string
    {
        $nome = User::query()->find($identificador)?->name;
        if (is_string($nome) && trim($nome) !== '') {
            return $nome;
        }

        $local = $mesa?->participanteLocal($identificador)?->nome;
        if (is_string($local) && trim($local) !== '') {
            return $local;
        }

        return null;
    }

    /**
     * @param array<string, string> $variaveis
     * @return array{momento: Momento, variaveis: array<string, string>, urgente: bool, chave: string}
     */
    private function situacao(Momento $momento, array $variaveis, bool $urgente, string $chave): array
    {
        return ['momento' => $momento, 'variaveis' => $variaveis, 'urgente' => $urgente, 'chave' => $chave];
    }

    /**
     * @param list<array{momento: Momento, variaveis: array<string, string>, urgente: bool, chave: string}> $comuns
     */
    private function comumAindaVerdadeira(array $comuns): ?string
    {
        $guardada = $this->sessao->get(self::SESSAO);
        if (!is_array($guardada)) {
            return null;
        }

        $chave = $guardada['chave'] ?? null;
        $texto = $guardada['texto'] ?? null;
        $momento = $guardada['momento'] ?? null;
        if (!is_string($chave) || !is_string($texto) || $texto === '' || !is_string($momento)) {
            return null;
        }

        foreach ($comuns as $situacao) {
            if ($situacao['chave'] === $chave && $situacao['momento']->value === $momento) {
                return $texto;
            }
        }

        return null;
    }

    /**
     * @param array{momento: Momento, variaveis: array<string, string>, urgente: bool, chave: string} $situacao
     */
    private function falar(array $situacao): ?Fala
    {
        $texto = $this->provocador->sortear(
            $situacao['momento'],
            $situacao['urgente'] ? 2 : 1,
            $situacao['variaveis'],
            Perspectiva::Pessoa,
        );
        if ($texto === null) {
            return null;
        }

        return $situacao['urgente']
            ? new Fala($texto, 'observando', 'atento')
            : new Fala($texto, 'base', 'neutro');
    }
}
