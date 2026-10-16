<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class MapasLabirintoSeeder extends Seeder
{
    public function run(): void
    {
        $json = file_get_contents(__DIR__.'/mapas-labirinto.json');
        if ($json === false) {
            throw new \RuntimeException('Não foi possível ler o catálogo inicial.');
        }
        /** @var list<array{codigo: string, nome: string, linhas: list<string>}> $mapas */
        $mapas = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        // Tudo ou nada. Conflitos com mapas manuais exigem decisão do administrador.
        DB::transaction(function () use ($mapas): void {
            $catalogo = app(CatalogoDeMapasLabirinto::class);
            foreach ($mapas as $dados) {
                if (DB::table('mapas_labirinto')->where('codigo_seed', $dados['codigo'])->exists()) {
                    continue;
                }
                $mapa = $catalogo->salvar(null, 0, $dados['nome'], $dados['linhas'], true, null);
                DB::table('mapas_labirinto')->where('id', $mapa['id'])->update(['codigo_seed' => $dados['codigo']]);
            }
        });
    }
}
