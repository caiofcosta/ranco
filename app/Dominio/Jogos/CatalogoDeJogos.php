<?php

declare(strict_types=1);

namespace App\Dominio\Jogos;

use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\Criterio;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\RegraViolada;

/** Configuração de cada jogo: critério de comparação, limites de jogadores, pontos, prazo padrão da vez e modos aceitos. */
final class CatalogoDeJogos
{
    /** Jogo padrão. Vale quando a partida não gravou o jogo. */
    public const LABIRINTO = 'labirinto';

    public const META_PADRAO_PONTOS = 5;

    public const LIMITE_PADRAO_RODADAS = 5;

    private const JOGOS = [
        self::LABIRINTO => [
            'nome' => 'Labirinto',
            'logo' => 'images/jogos/labirinto.svg',
            'criterio' => Criterio::MenorMelhor,
            'minimoJogadores' => 2,
            'maximoJogadores' => 8,
            'pontosPorPosicao' => [3, 2, 1],
            'prazoVezPadraoSegundos' => 120,
            // Cada pessoa joga sozinha contra o relógio: funciona nos dois modos.
            'modos' => [ModoExecucao::Sequencial, ModoExecucao::Paralelo],
        ],
    ];

    /** @return list<array{id: string, nome: string, logo: string, minimo: int, maximo: int}> */
    public static function disponiveis(): array
    {
        $jogos = [];
        foreach (self::JOGOS as $id => $definicao) {
            $jogos[] = [
                'id' => $id,
                'nome' => $definicao['nome'],
                'logo' => $definicao['logo'],
                'minimo' => $definicao['minimoJogadores'],
                'maximo' => $definicao['maximoJogadores'],
            ];
        }

        return $jogos;
    }

    public static function nome(string $jogo): string
    {
        return self::JOGOS[$jogo]['nome'] ?? $jogo;
    }

    public static function logo(string $jogo): ?string
    {
        return self::JOGOS[$jogo]['logo'] ?? null;
    }

    /** @return list<ModoExecucao> Modos que o admin pode escolher para o jogo, o primeiro é o padrão. */
    public static function modos(string $jogo): array
    {
        return self::JOGOS[$jogo]['modos'] ?? [];
    }

    /** Jogos cuja vez pode ser assistida ao vivo (transmissao cliente-a-cliente da jogada). */
    private const TRANSMITEM_AO_VIVO = [self::LABIRINTO];

    public static function transmiteAoVivo(string $jogo): bool
    {
        return in_array($jogo, self::TRANSMITEM_AO_VIVO, true);
    }

    /** Sem prazo informado pelo admin, vale o prazo padrão do jogo; sem modo, o primeiro que o jogo aceita. */
    public static function configuracao(
        string $jogo,
        ?int $prazoVezSegundos,
        ?int $metaPontos,
        ?int $limiteRodadas,
        ?ModoExecucao $modo = null,
        ?array $pontosPorPosicao = null,
    ): ConfiguracaoPartida {
        $definicao = self::JOGOS[$jogo] ?? throw new RegraViolada("Jogo desconhecido: {$jogo}.");
        $modo ??= $definicao['modos'][0];
        if (! in_array($modo, $definicao['modos'], true)) {
            throw new RegraViolada("{$definicao['nome']} não aceita o modo {$modo->value}.");
        }

        if ($pontosPorPosicao !== null) {
            if (! array_is_list($pontosPorPosicao) || count($pontosPorPosicao) < 1 || count($pontosPorPosicao) > $definicao['maximoJogadores']) {
                throw new RegraViolada('Informe os pontos por colocação em uma lista válida.');
            }
            $anterior = PHP_INT_MAX;
            foreach ($pontosPorPosicao as $pontos) {
                if (! is_int($pontos) || $pontos < 0 || $pontos > $anterior) {
                    throw new RegraViolada('Use pontos inteiros a partir de zero, sem aumentar nas colocações seguintes.');
                }
                $anterior = $pontos;
            }
            if ($metaPontos !== null && $pontosPorPosicao[0] === 0) {
                throw new RegraViolada('Para usar uma meta, pelo menos o primeiro lugar precisa receber pontos.');
            }
        }

        return new ConfiguracaoPartida(
            minimoJogadores: $definicao['minimoJogadores'],
            maximoJogadores: $definicao['maximoJogadores'],
            prazoVezSegundos: $prazoVezSegundos ?? $definicao['prazoVezPadraoSegundos'],
            criterio: $definicao['criterio'],
            pontosPorPosicao: $pontosPorPosicao ?? $definicao['pontosPorPosicao'],
            metaPontos: $metaPontos,
            limiteRodadas: $limiteRodadas,
            jogo: $jogo,
            modo: $modo,
        );
    }
}
