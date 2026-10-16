<?php

declare(strict_types=1);

namespace App\Inicio;

use App\Dominio\Plataforma\Plataforma;

/**
 * "Suas mesas" da Início: as mesas da pessoa, os convites pendentes e os resultados. A página e a
 * atualização ao vivo leem daqui, para que os dois nunca divirjam.
 */
final class SuasMesasDaHome
{
    /** @return array{mesas: list<MesaNaHome>, convites: list<ConviteNaHome>, resultados: list<array{id: string, nome: string}>, vagasDeMesa: int, invisivel: bool} */
    public static function para(Plataforma $plataforma, string $usuario): array
    {
        $mesas = $plataforma->mesasAbertasDe($usuario);
        $ids = array_map(static fn ($mesa): string => $mesa->id, $mesas);

        // Convite de mesa em que a pessoa já está não vira um segundo cartão.
        $convites = array_values(array_filter(
            ConviteNaHome::para($plataforma, $usuario),
            static fn (ConviteNaHome $convite): bool => ! in_array($convite->mesaId, $ids, true),
        ));
        $resultados = array_values(array_filter(
            $plataforma->resultadosPendentesDe($usuario),
            static fn (array $resultado): bool => ! in_array($resultado['id'], $ids, true),
        ));

        return [
            'mesas' => array_map(static fn ($mesa): MesaNaHome => MesaNaHome::de($mesa, $usuario), $mesas),
            'convites' => $convites,
            'resultados' => $resultados,
            'vagasDeMesa' => $plataforma->vagasDeMesa($usuario),
            'invisivel' => $plataforma->estaInvisivel($usuario),
        ];
    }
}
