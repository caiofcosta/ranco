<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto as Catalogo;
use App\Dominio\Jogos\Labirinto\ColisaoMapa;
use App\Dominio\Jogos\Labirinto\RepositorioMapasLabirinto;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use stdClass;

/** @phpstan-import-type Mapa from Catalogo */
final class CatalogoDeMapasLabirinto implements RepositorioMapasLabirinto
{
    /** @return list<Mapa> */
    public function listar(): array
    {
        return array_values(DB::table('mapas_labirinto')->orderBy('nome')->orderBy('id')
            ->get()->map(fn (stdClass $linha): array => $this->converter($linha))->all());
    }

    /** @return Mapa */
    public function buscar(int $id): array
    {
        return $this->converter(DB::table('mapas_labirinto')->where('id', $id)->firstOrFail());
    }

    /** @return list<stdClass> */
    public function versoes(int $id): array
    {
        return array_values(DB::table('versoes_labirinto')->where('mapa_id', $id)
            ->orderByDesc('numero')->get()->all());
    }

    /** @return array{id: int, nome: string, tipo: string}|null */
    public function conflito(?int $id, string $chaveNome, string $hash): ?array
    {
        $mesmoNome = DB::table('mapas_labirinto')
            ->where('chave_nome', $chaveNome)
            ->when($id !== null, fn ($consulta) => $consulta->where('id', '<>', $id))->first();
        if ($mesmoNome !== null) {
            return ['id' => (int) $mesmoNome->id, 'nome' => (string) $mesmoNome->nome, 'tipo' => 'nome'];
        }
        $mesmoDesenho = DB::table('desenhos_labirinto')->join('mapas_labirinto', 'mapas_labirinto.id', '=', 'desenhos_labirinto.mapa_id')
            ->where('hash', $hash)
            ->when($id !== null, fn ($consulta) => $consulta->where('mapa_id', '<>', $id))
            ->select('mapas_labirinto.id', 'mapas_labirinto.nome')->first();
        if ($mesmoDesenho !== null) {
            return ['id' => (int) $mesmoDesenho->id, 'nome' => (string) $mesmoDesenho->nome, 'tipo' => 'desenho'];
        }

        return null;
    }

    /** @return Mapa */
    public function bloquear(int $id): array
    {
        return $this->converter(DB::table('mapas_labirinto')->where('id', $id)->lockForUpdate()->firstOrFail());
    }

    /** @param Mapa $mapa */
    public function gravar(?int $id, array $mapa, string $chaveNome, DateTimeImmutable $agora): int
    {
        $dados = ['nome' => $mapa['nome'], 'chave_nome' => $chaveNome,
            'rascunho' => json_encode($mapa['linhas'], JSON_THROW_ON_ERROR), 'revisao' => $mapa['revisao'],
            'ativo' => $mapa['ativo'], 'versao_publicada' => $mapa['versao_publicada'], 'updated_at' => $agora];
        try {
            if ($id === null) {
                return DB::table('mapas_labirinto')->insertGetId([...$dados, 'created_at' => $agora]);
            }
            DB::table('mapas_labirinto')->where('id', $id)->update($dados);

            return $id;
        } catch (UniqueConstraintViolationException $erro) {
            throw new ColisaoMapa(previous: $erro);
        }
    }

    public function reservar(int $id, string $hash): void
    {
        $reserva = DB::table('desenhos_labirinto')->where('hash', $hash)->first();
        if ($reserva !== null) {
            if ((int) $reserva->mapa_id !== $id) {
                throw new ColisaoMapa;
            }

            return;
        }
        try {
            DB::table('desenhos_labirinto')->insert(['hash' => $hash, 'mapa_id' => $id]);
        } catch (UniqueConstraintViolationException $erro) {
            throw new ColisaoMapa(previous: $erro);
        }
    }

    public function publicar(int $id, int $numero, string $nome, array $definicao, ?int $autor, DateTimeImmutable $agora): void
    {
        DB::table('versoes_labirinto')->insert([
            'mapa_id' => $id, 'numero' => $numero, 'nome' => $nome,
            'desenho' => json_encode($definicao, JSON_THROW_ON_ERROR),
            'publicador_id' => $autor, 'publicada_em' => $agora,
        ]);
    }

    public function liberarRascunhos(int $id, array $hashes): void
    {
        DB::table('desenhos_labirinto')->where('mapa_id', $id)->whereNotIn('hash', $hashes)->delete();
    }

    /** @return Mapa */
    private function converter(stdClass $linha): array
    {
        /** @var list<string> $linhas */
        $linhas = json_decode($linha->rascunho, true, 512, JSON_THROW_ON_ERROR);

        return [
            'id' => (int) $linha->id,
            'nome' => (string) $linha->nome,
            'linhas' => $linhas,
            'ativo' => (bool) $linha->ativo,
            'revisao' => (int) $linha->revisao,
            'versao_publicada' => $linha->versao_publicada === null ? null : (int) $linha->versao_publicada,
            'usos_em_rodadas' => $linha->usos_em_rodadas === null ? null : (int) $linha->usos_em_rodadas,
        ];
    }
}
