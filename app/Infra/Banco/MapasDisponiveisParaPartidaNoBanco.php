<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Jogos\Labirinto\MapasDisponiveisParaPartida;
use Illuminate\Support\Facades\DB;

final class MapasDisponiveisParaPartidaNoBanco implements MapasDisponiveisParaPartida
{
    public function listar(): array
    {
        $linhas = DB::table('mapas_labirinto as m')->join('versoes_labirinto as v', function ($join) {
            $join->on('v.mapa_id', '=', 'm.id')->on('v.numero', '=', 'm.versao_publicada');
        })->where('m.ativo', true)->orderBy('m.id')->select('v.id', 'v.mapa_id', 'v.nome', 'v.desenho')
            ->get();
        $versoes = [];
        foreach ($linhas as $v) {
            /** @var array{formato: int, linhas: list<string>, tamanho_celula: int, raio_bolinha: int, velocidade: int} $definicao */
            $definicao = json_decode($v->desenho, true, 512, JSON_THROW_ON_ERROR);
            $versoes[] = [
                'id' => (int) $v->id, 'mapa_id' => (int) $v->mapa_id, 'nome' => (string) $v->nome,
                'definicao' => $definicao,
            ];
        }

        return $versoes;
    }
}
