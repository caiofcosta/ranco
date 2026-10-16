<?php

declare(strict_types=1);

namespace App\Conquista;

use App\Dominio\Conquista\ConcessorDeMedalhas;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\MotivoEncerramento;
use App\Dominio\Partida\Partida;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocador;
use App\Mesa\NomeDaMesa;
use App\Mesa\PessoasDaMesa;

/** Concede uma medalha por pessoa, pela colocação final da partida. */
final class ConcedeMedalhaDaPartida implements ConcessorDeMedalhas
{
    public function __construct(private readonly Provocador $provocador) {}

    public function concederPendentes(Mesa $mesa): void
    {
        $jaConcedidas = Medalhas::concedidasNaMesa($mesa->id);
        $pendencias = [];

        foreach ($mesa->partidas() as $indice => $partida) {
            $numero = $indice + 1;
            $jogo = $partida->configuracao()->jogo ?? CatalogoDeJogos::LABIRINTO;
            $wo = $partida->motivoEncerramento() === MotivoEncerramento::Desistencia;

            foreach ($partida->classificacao() as $linha) {
                if ($linha['colocacao'] > 3) {
                    continue;
                }
                if ($wo && $linha['colocacao'] !== 1) {
                    continue;
                }
                if (isset($jaConcedidas[$numero][$linha['pessoa']])) {
                    continue;
                }

                $pendencias[] = [$numero, $partida, $linha, $jogo];
            }
        }

        if ($pendencias === []) {
            return;
        }

        foreach ($pendencias as [$numero, $partida, $linha, $jogo]) {
            $momento = self::momento($partida, $linha['colocacao'], $linha['empate']);
            $variaveis = self::variaveis($mesa, $partida, $linha['pessoa'], $linha['colocacao'], $linha['empate']);

            Medalhas::conceder(
                $mesa->id,
                $numero,
                $linha['pessoa'],
                $linha['colocacao'],
                $linha['empate'],
                $jogo,
                $this->provocador->sortear($momento, 2, $variaveis, Perspectiva::Pessoa),
                NomeDaMesa::de($mesa),
                $partida->criadaEm(),
            );
        }
    }

    private static function momento(Partida $partida, int $colocacao, bool $empate): Momento
    {
        if ($colocacao === 1 && $partida->motivoEncerramento() === MotivoEncerramento::Desistencia) {
            return Momento::VitoriaPorDesistencia;
        }

        return match ([$colocacao, $empate]) {
            [1, false] => Momento::Vitoria,
            [2, false] => Momento::SegundoLugar,
            [3, false] => Momento::TerceiroLugar,
            [1, true] => Momento::PrimeiroLugarEmpate,
            [2, true] => Momento::SegundoLugarEmpate,
            [3, true] => Momento::TerceiroLugarEmpate,
        };
    }

    /**
     * Nomes reais, nunca o identificador. Sem nome, a variável não entra e a frase que a exige não sai.
     *
     * @return array<string, string>
     */
    private static function variaveis(Mesa $mesa, Partida $partida, string $pessoa, int $colocacao, bool $empate): array
    {
        $variaveis = [];
        $vencedores = $partida->vencedores();
        if (count($vencedores) === 1) {
            $nome = PessoasDaMesa::nome($mesa, $vencedores[0]);
            if ($nome !== null) {
                $variaveis['vencedor'] = $nome;
            }
        }

        if (!$empate) {
            return $variaveis;
        }

        $nomes = [];
        foreach ($partida->classificacao() as $linha) {
            if ($linha['colocacao'] !== $colocacao || $linha['pessoa'] === $pessoa) {
                continue;
            }
            $nome = PessoasDaMesa::nome($mesa, $linha['pessoa']);
            if ($nome !== null) {
                $nomes[] = $nome;
            }
        }
        if ($nomes !== []) {
            $variaveis['outro'] = PessoasDaMesa::juntar($nomes);
        }

        return $variaveis;
    }
}
