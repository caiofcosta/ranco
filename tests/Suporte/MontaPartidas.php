<?php

declare(strict_types=1);

namespace Tests\Suporte;

use DateTimeImmutable;
use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\Origem;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\TipoEvento;

/**
 * Apoio para testes de partida: a primeira pessoa informada é sempre a admin.
 */
trait MontaPartidas
{
    private const PRAZO = 300;

    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-01 10:00:00'));
    }

    private function configuracao(mixed ...$ajustes): ConfiguracaoPartida
    {
        return new ConfiguracaoPartida(...[
            'minimoJogadores' => 2,
            'maximoJogadores' => 6,
            'prazoVezSegundos' => self::PRAZO,
            ...$ajustes,
        ]);
    }

    /** @param list<string> $jogadores */
    private function partidaIniciada(array $jogadores = ['ana', 'bruno', 'carla'], ?ConfiguracaoPartida $configuracao = null): Partida
    {
        $admin = array_shift($jogadores);
        $partida = Partida::criar($configuracao ?? $this->configuracao(), $admin, $this->relogio);
        foreach ($jogadores as $jogador) {
            $partida->adicionarParticipante($jogador);
        }
        $partida->iniciar(por: $admin);

        return $partida;
    }

    private function jogar(Partida $partida, string $jogador, int $resultado): void
    {
        $partida->iniciarTentativa($jogador);
        $partida->concluirTentativa($jogador, resultado: $resultado);
    }

    /** @param array<string, int> $resultados Na ordem da vez. */
    private function jogarFase(Partida $partida, array $resultados): void
    {
        foreach ($resultados as $jogador => $resultado) {
            $this->jogar($partida, (string) $jogador, $resultado);
        }
    }

    private function assertEventoRegistrado(Partida $partida, TipoEvento $tipo, Origem $origem): void
    {
        foreach ($partida->eventos() as $evento) {
            if ($evento->tipo === $tipo && $evento->origem === $origem) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("Evento {$tipo->value} com origem {$origem->value} não registrado.");
    }
}
