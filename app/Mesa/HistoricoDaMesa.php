<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use DateTimeZone;

/**
 * Partidas encerradas da mesa, na ordem de criação: jogo, data, vencedor e placar final.
 * O número é a posição na lista de partidas da mesa, começando em 1. O placar segue a colocação
 * de `Partida::classificacao()`; partida sem vencedor não tem colocação e mantém a ordem de entrada.
 */
final readonly class HistoricoDaMesa
{
    /**
     * @param  list<array{numero: int, jogo: string, data: ?string, vencedor: ?string, placar: list<array{id: string, nome: string, pontos: int, colocacao: ?int, empate: bool}>}>  $partidas
     */
    public function __construct(public array $partidas) {}

    public static function de(Mesa $mesa): self
    {
        PessoasDaMesa::preparar($mesa);
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $partidas = [];
        foreach ($mesa->partidas() as $indice => $partida) {
            if ($partida->situacao() !== SituacaoPartida::Encerrada) {
                continue;
            }

            $classificacao = $partida->classificacao();
            $colocacoes = array_column($classificacao, 'colocacao', 'pessoa');
            $empates = array_column($classificacao, 'empate', 'pessoa');
            $placar = [];
            foreach ($partida->placar() as $linha) {
                $placar[] = [
                    'id' => $linha['participante'],
                    'nome' => PessoasDaMesa::nome($mesa, $linha['participante'])
                        ?? 'Participante indisponível',
                    'pontos' => $linha['pontos'],
                    'colocacao' => $colocacoes[$linha['participante']] ?? null,
                    'empate' => $empates[$linha['participante']] ?? false,
                ];
            }
            usort($placar, static fn (array $a, array $b): int => ($a['colocacao'] ?? PHP_INT_MAX) <=> ($b['colocacao'] ?? PHP_INT_MAX));

            $nomes = [];
            foreach ($partida->vencedores() as $id) {
                $nomes[] = PessoasDaMesa::nome($mesa, $id) ?? 'Participante indisponível';
            }
            $partidas[] = [
                'numero' => $indice + 1,
                'jogo' => CatalogoDeJogos::nome($partida->configuracao()->jogo ?? CatalogoDeJogos::LABIRINTO),
                'data' => $partida->criadaEm()?->setTimezone($fuso)->format('d/m/Y'),
                'vencedor' => $nomes === [] ? null : PessoasDaMesa::juntar($nomes),
                'placar' => $placar,
            ];
        }

        return new self($partidas);
    }
}
