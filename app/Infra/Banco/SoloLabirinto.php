<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Jogos\Labirinto\RepositorioSoloLabirinto;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class SoloLabirinto implements RepositorioSoloLabirinto
{
    /** @return list<stdClass> */
    public function disponiveis(int $pessoa): array
    {
        return array_values($this->publicadas()->select('versoes_labirinto.*')
            ->selectSub(DB::table('tentativas_solo_labirinto')->selectRaw('MIN(tempo)')
                ->where('pessoa_id', $pessoa)->whereColumn('versao_id', 'versoes_labirinto.id'), 'melhor_tempo')
            ->orderBy('versoes_labirinto.nome')->get()->all());
    }

    public function versaoDisponivel(?int $mapa): ?int
    {
        $versao = $this->publicadas()->when($mapa !== null, fn (Builder $q) => $q->where('mapas_labirinto.id', $mapa))
            ->select('versoes_labirinto.id')->inRandomOrder()->lockForUpdate()->first();

        return $versao === null ? null : (int) $versao->id;
    }

    public function criar(int $pessoa, int $versao, DateTimeImmutable $agora): string
    {
        $id = (string) Str::uuid();
        DB::table('tentativas_solo_labirinto')->insert([
            'id' => $id, 'pessoa_id' => $pessoa, 'versao_id' => $versao, 'iniciada_em' => $agora,
        ]);

        return $id;
    }

    public function buscar(int $pessoa, string $id): stdClass
    {
        return DB::table('tentativas_solo_labirinto as t')->join('versoes_labirinto as v', 'v.id', '=', 't.versao_id')
            ->where('t.pessoa_id', $pessoa)->where('t.id', $id)
            ->select('t.*', 'v.nome', 'v.numero', 'v.mapa_id', 'v.desenho')->firstOrFail();
    }

    public function travar(int $pessoa, string $id): void
    {
        DB::table('users')->where('id', $pessoa)->lockForUpdate()->firstOrFail();
        DB::table('tentativas_solo_labirinto')->where('pessoa_id', $pessoa)->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    public function melhor(int $pessoa, int $versao): ?int
    {
        $tempo = $this->tempos($pessoa, $versao)->min('tempo');

        return $tempo === null ? null : (int) $tempo;
    }

    public function registrar(string $id, int $tempo, bool $recorde, DateTimeImmutable $agora): void
    {
        DB::table('tentativas_solo_labirinto')->where('id', $id)->update([
            'tempo' => $tempo, 'recorde' => $recorde, 'concluida_em' => $agora,
        ]);
    }

    public function removerAbertasAntesDe(DateTimeImmutable $limite): int
    {
        // Cada DELETE repete o predicado: uma conclusão concorrente nunca é removida.
        $total = 0;
        do {
            $removidas = DB::table('tentativas_solo_labirinto')->whereNull('tempo')->whereNull('concluida_em')
                ->where('iniciada_em', '<', $limite)->limit(1000)->delete();
            $total += $removidas;
        } while ($removidas === 1000);

        return $total;
    }

    /** @return array{melhorTempo: int|null, melhores: list<stdClass>, ultimas: list<stdClass>} */
    public function historico(int $pessoa, int $versao): array
    {
        $melhor = $this->tempos($pessoa, $versao)->min('tempo');

        return [
            'melhorTempo' => $melhor === null ? null : (int) $melhor,
            'melhores' => array_values($this->tempos($pessoa, $versao)->orderBy('tempo')->orderBy('concluida_em')->orderBy('id')->limit(10)->get()->all()),
            'ultimas' => array_values($this->tempos($pessoa, $versao)->orderByDesc('concluida_em')->orderByDesc('id')->limit(10)->get()->all()),
        ];
    }

    private function publicadas(): Builder
    {
        return DB::table('mapas_labirinto')->join('versoes_labirinto', function ($join) {
            $join->on('versoes_labirinto.mapa_id', '=', 'mapas_labirinto.id')
                ->on('versoes_labirinto.numero', '=', 'mapas_labirinto.versao_publicada');
        })->where('mapas_labirinto.ativo', true);
    }

    private function tempos(int $pessoa, int $versao): Builder
    {
        return DB::table('tentativas_solo_labirinto')->where('pessoa_id', $pessoa)->where('versao_id', $versao)->whereNotNull('tempo');
    }
}
