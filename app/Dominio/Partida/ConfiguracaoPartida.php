<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

use App\Dominio\RegraViolada;

final readonly class ConfiguracaoPartida
{
    /**
     * @param list<int> $pontosPorPosicao Pontos da 1ª posição em diante; posições além da lista valem 0.
     */
    public function __construct(
        public int $minimoJogadores,
        public int $maximoJogadores,
        public int $prazoVezSegundos,
        public Criterio $criterio = Criterio::MaiorMelhor,
        public array $pontosPorPosicao = [3, 2, 1],
        public ?int $metaPontos = null,
        public ?int $limiteRodadas = null,
        public ?string $jogo = null,
        public ModoExecucao $modo = ModoExecucao::Sequencial,
        public bool $intervaloEntreRodadas = false,
    ) {
        if ($minimoJogadores < 1 || $maximoJogadores < $minimoJogadores) {
            throw new RegraViolada('Limites de jogadores inválidos.');
        }
        if ($prazoVezSegundos < 1) {
            throw new RegraViolada('O prazo da vez é obrigatório e precisa ser positivo.');
        }
        if (($metaPontos !== null && $metaPontos < 0) || ($limiteRodadas !== null && $limiteRodadas < 0)) {
            throw new RegraViolada('Meta de pontos e limite de rodadas não podem ser negativos.');
        }
        if ($metaPontos !== null && $limiteRodadas !== null) {
            throw new RegraViolada('Escolha apenas uma forma de encerramento: meta de pontos ou limite de rodadas.');
        }
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * Partida gravada antes do modo existir era sequencial.
     *
     * @param array<string, mixed> $dados
     */
    public function __unserialize(array $dados): void
    {
        $dados['modo'] ??= ModoExecucao::Sequencial;

        foreach ($dados as $propriedade => $valor) {
            $this->{$propriedade} = $valor;
        }
    }
}
