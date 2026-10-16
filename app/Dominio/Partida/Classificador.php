<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

final class Classificador
{
    /**
     * Agrupa os participantes por resultado, do melhor para o pior. Empatados ficam no mesmo grupo,
     * na ordem em que foram informados.
     *
     * @param array<string, int> $resultados
     * @return list<list<string>>
     */
    public static function agrupar(array $resultados, Criterio $criterio): array
    {
        $valores = array_values(array_unique(array_values($resultados)));
        if ($criterio === Criterio::MaiorMelhor) {
            rsort($valores);
        } else {
            sort($valores);
        }

        $grupos = [];
        foreach ($valores as $valor) {
            $grupos[] = array_map('strval', array_keys($resultados, $valor, true));
        }

        return $grupos;
    }
}
