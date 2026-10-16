<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Jogos;

use App\Dominio\Jogos\Labirinto\MapasDaPartida;
use App\Dominio\RegraViolada;
use PHPUnit\Framework\TestCase;

final class MapasDaPartidaTest extends TestCase
{
    public function test_nao_repete_antes_de_esgotar_nem_na_troca_de_ciclo_e_preserva_historico(): void
    {
        $versoes = array_map(fn ($id) => ['id' => $id, 'mapa_id' => $id, 'nome' => 'Mapa '.$id, 'definicao' => []], [1, 2, 3]);
        $sorteio = new MapasDaPartida($versoes);
        $historico = [];
        for ($rodada = 1; $rodada <= 30; $rodada++) {
            $sorteio->sortear($rodada);
            $mapa = $sorteio->daRodada($rodada);
            $historico[$rodada] = $mapa['id'];
            $sorteio->sortear($rodada);
            self::assertSame($mapa, $sorteio->daRodada($rodada));
            if ($rodada > 1) {
                self::assertNotSame($historico[$rodada - 1], $mapa['id']);
            }
            $sorteio = unserialize(serialize($sorteio));
        }
        foreach (array_chunk($historico, 3) as $ciclo) {
            self::assertCount(3, array_unique($ciclo));
        }
        foreach ($historico as $rodada => $id) {
            self::assertSame($id, $sorteio->daRodada($rodada)['id']);
        }
    }

    public function test_um_mapa_pode_ser_reutilizado(): void
    {
        $sorteio = new MapasDaPartida([['id' => 1, 'mapa_id' => 1, 'nome' => 'Único', 'definicao' => []]]);
        $sorteio->sortear(1);
        $sorteio->sortear(2);
        self::assertSame($sorteio->daRodada(1), $sorteio->daRodada(2));
    }

    public function test_conjunto_vazio_impede_inicio(): void
    {
        $this->expectException(RegraViolada::class);
        new MapasDaPartida([]);
    }
}
