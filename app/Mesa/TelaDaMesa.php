<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\PendenciaParaIniciar;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Partida\SituacaoPartida;

/**
 * O que a tela da mesa mostra: nome, situação da sala e da partida, se houver, e o que falta para
 * iniciar a próxima partida. As cadeiras vêm de `CadeirasDaMesa`.
 */
final readonly class TelaDaMesa
{
    /**
     * @param  list<string>  $pendencias  Textos de `Mesa::pendenciasParaIniciar`, na mesma ordem.
     */
    public function __construct(
        public string $id,
        public string $nome,
        public string $nomeGravado,
        public bool $ehAdmin,
        public ?string $situacaoDaPartida,
        public string $situacaoDaSala,
        public array $pendencias,
    ) {}

    public static function de(Mesa $mesa, string $usuario): self
    {
        return new self(
            $mesa->id,
            NomeDaMesa::de($mesa),
            $mesa->nome(),
            $mesa->admin() === $usuario,
            self::situacaoDaPartida($mesa, $usuario),
            match ($mesa->situacao()) {
                SituacaoMesa::Aberta => 'Sala aberta',
                SituacaoMesa::Encerrada => 'Sala encerrada',
            },
            self::pendencias($mesa),
        );
    }

    private static function situacaoDaPartida(Mesa $mesa, string $usuario): ?string
    {
        $partida = $mesa->partidaAtiva();
        if ($partida === null) {
            return null;
        }
        if ($partida->pausada()) {
            return 'Partida pausada.';
        }
        if ($partida->situacao() === SituacaoPartida::EmAndamento && $partida->vezAtual() === $usuario) {
            return 'É a sua vez.';
        }
        if ($partida->situacao() === SituacaoPartida::EmAndamento) {
            return 'Partida em andamento.';
        }
        if ($partida->situacao() === SituacaoPartida::EmConfiguracao) {
            return 'Partida em configuração.';
        }

        return null;
    }

    /** @return list<string> */
    private static function pendencias(Mesa $mesa): array
    {
        $partida = $mesa->partidaAtiva();

        return array_map(static fn (PendenciaParaIniciar $pendencia): string => match ($pendencia) {
            PendenciaParaIniciar::EscolherJogo => 'Escolher o jogo.',
            PendenciaParaIniciar::MinimoDeJogadores => 'Chamar mais jogadores: o jogo pede pelo menos '.$partida?->configuracao()->minimoJogadores.'.',
        }, $mesa->pendenciasParaIniciar());
    }
}
