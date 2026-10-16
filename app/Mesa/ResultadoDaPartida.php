<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Conquista\Medalhas;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;

/**
 * Resultado de uma partida encerrada, para a modal da mesa. Só compõe dados existentes: colocação,
 * empate e pontos da partida vêm do HistoricoDaMesa; as medalhas, da tabela `medalhas`; vencedor e
 * frase, de PartidaNaMesa; as rodadas, do que a Partida gravou. Não pontua nem classifica nada.
 */
final readonly class ResultadoDaPartida
{
    /**
     * @param  list<array{id: string, nome: string, pontos: int, colocacao: ?int, empate: bool, medalha: ?array{arquivo: string, descricao: string}}>  $classificacao
     * @param  list<array{numero: int, linhas: list<array{id: string, nome: string, posicao: ?int, resultado: string, pontos: int}>}>  $rodadas
     */
    public function __construct(
        public int $numero,
        public string $jogo,
        public ?string $logo,
        public PartidaNaMesa $partida,
        public array $classificacao,
        public array $rodadas,
    ) {}

    public static function de(Mesa $mesa, string $usuario, int $numero, HistoricoDaMesa $historico): ?self
    {
        $partida = $mesa->partidas()[$numero - 1] ?? null;
        $item = array_values(array_filter($historico->partidas, static fn (array $p): bool => $p['numero'] === $numero))[0] ?? null;
        if ($partida?->situacao() !== SituacaoPartida::Encerrada || $item === null) {
            return null;
        }

        $medalhas = Medalhas::daPartida($mesa->id, $numero);
        $classificacao = array_map(static fn (array $linha): array => [
            ...$linha,
            'medalha' => isset($medalhas[$linha['id']])
                ? Medalhas::imagem($medalhas[$linha['id']]['colocacao'], $medalhas[$linha['id']]['empate'])
                : null,
        ], $item['placar']);

        $rodadas = [];
        for ($rodada = 1; $rodada <= $partida->rodadaAtual(); $rodada++) {
            $linhas = [];
            foreach ($partida->participantesDaRodada($rodada) as $id) {
                $id = (string) $id;
                $tentativa = $partida->tentativaDe($rodada, $id);
                $linhas[] = [
                    'id' => $id,
                    'nome' => PessoasDaMesa::nome($mesa, $id) ?? 'Participante indisponível',
                    'posicao' => $partida->posicaoNaRodada($rodada, $id),
                    'resultado' => match ($tentativa?->situacao) {
                        SituacaoTentativa::Concluida => TelaDoJogo::textoDoResultado($partida, $tentativa),
                        SituacaoTentativa::Interrompida => 'Interrompida',
                        default => 'Não jogou',
                    },
                    'pontos' => $partida->pontosNaRodada($rodada, $id),
                ];
            }
            usort($linhas, static fn (array $a, array $b): int => ($a['posicao'] ?? PHP_INT_MAX) <=> ($b['posicao'] ?? PHP_INT_MAX));
            if ($linhas !== []) {
                $rodadas[] = ['numero' => $rodada, 'linhas' => $linhas];
            }
        }

        return new self(
            $numero,
            $item['jogo'],
            CatalogoDeJogos::logo($partida->configuracao()->jogo ?? CatalogoDeJogos::LABIRINTO),
            PartidaNaMesa::de($mesa, $usuario, $partida),
            $classificacao,
            $rodadas,
        );
    }
}
