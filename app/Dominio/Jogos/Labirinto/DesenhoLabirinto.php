<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use App\Dominio\RegraViolada;

final class DesenhoLabirinto
{
    public const TAMANHO = 13;

    /** @return list<string> */
    public static function vazio(): array
    {
        return ['#############', ...array_fill(0, 11, '#...........#'), '#############'];
    }

    /** @param array<mixed> $linhas */
    public static function validarFormato(array $linhas): void
    {
        if (! array_is_list($linhas) || count($linhas) !== self::TAMANHO) {
            throw new RegraViolada('O mapa precisa ter 13 linhas.');
        }

        foreach ($linhas as $linha) {
            if (! is_string($linha) || preg_match('/^[#.IC]{13}$/D', $linha) !== 1) {
                throw new RegraViolada('Cada linha precisa ter 13 células válidas.');
            }
        }
    }

    /** @param list<string> $linhas */
    public static function validarPublicacao(array $linhas): void
    {
        self::validarFormato($linhas);

        $inicio = null;
        $chegada = null;
        foreach ($linhas as $y => $linha) {
            foreach (str_split($linha) as $x => $celula) {
                if (($x === 0 || $y === 0 || $x === 12 || $y === 12) && $celula !== '#') {
                    throw new RegraViolada('A borda do mapa precisa estar fechada por paredes.');
                }
                if ($celula === 'I') {
                    if ($inicio !== null) {
                        throw new RegraViolada('Use apenas um início.');
                    }
                    $inicio = [$x, $y];
                }
                if ($celula === 'C') {
                    if ($chegada !== null) {
                        throw new RegraViolada('Use apenas uma chegada.');
                    }
                    $chegada = [$x, $y];
                }
            }
        }

        if ($inicio === null || $chegada === null) {
            throw new RegraViolada('Posicione um início e uma chegada.');
        }

        $fila = [$inicio];
        $visitadas = [$inicio[0].','.$inicio[1] => true];
        for ($indice = 0; $indice < count($fila); $indice++) {
            [$x, $y] = $fila[$indice];
            if ([$x, $y] === $chegada) {
                return;
            }
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                $chave = $nx.','.$ny;
                if ($nx < 0 || $ny < 0 || $nx >= 13 || $ny >= 13
                    || $linhas[$ny][$nx] === '#' || isset($visitadas[$chave])) {
                    continue;
                }
                $visitadas[$chave] = true;
                $fila[] = [$nx, $ny];
            }
        }

        throw new RegraViolada('Não existe caminho entre o início e a chegada.');
    }

    /**
     * @param  list<string>  $linhas
     * @return array{formato: int, linhas: list<string>, tamanho_celula: int, raio_bolinha: int, velocidade: int}
     */
    public static function definicao(array $linhas): array
    {
        self::validarPublicacao($linhas);

        return [
            'formato' => 1,
            'linhas' => $linhas,
            'tamanho_celula' => 40,
            'raio_bolinha' => 10,
            'velocidade' => 120,
        ];
    }

    /** @param array{linhas: list<string>, tamanho_celula: int, velocidade: int} $definicao */
    public static function tempoMinimo(array $definicao): int
    {
        $pontos = [];
        foreach ($definicao['linhas'] as $y => $linha) {
            foreach (str_split($linha) as $x => $celula) {
                if ($celula === 'I' || $celula === 'C') {
                    $pontos[$celula] = [$x, $y];
                }
            }
        }
        if (! isset($pontos['I'], $pontos['C'])) {
            throw new \LogicException('Versão publicada sem início ou chegada.');
        }
        // Limite físico conservador: distância em linha reta, tolerância da chegada e um frame.
        // Não prova que o trajeto foi percorrido; o resultado ainda vem do navegador.
        $distancia = hypot($pontos['I'][0] - $pontos['C'][0], $pontos['I'][1] - $pontos['C'][1]) * $definicao['tamanho_celula'];

        return max(1, (int) floor(($distancia - 12) / $definicao['velocidade'] * 1000) - 50);
    }
}
