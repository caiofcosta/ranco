<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Conquista\RepositorioDeTrofeus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class RepositorioDeTrofeusNoBanco implements RepositorioDeTrofeus
{
    public function dasPessoas(array $pessoas): array
    {
        return $this->resultados(DB::table('trofeus')->whereIn('pessoa', $pessoas));
    }

    public function daMesa(string $mesaId): array
    {
        return $this->resultados(DB::table('trofeus')->where('mesa_id', $mesaId));
    }

    public function conceder(
        string $mesaId,
        string $nomeDaMesa,
        array $premiados,
        DateTimeImmutable $quando,
        ?DateTimeImmutable $mesaCriadaEm = null,
    ): void {
        if ($premiados === []) {
            return;
        }

        $data = self::emUtc($quando);
        $criacao = self::emUtc($mesaCriadaEm);
        $linhas = array_map(static fn (array $premiado): array => [
            'mesa_id' => $mesaId,
            'mesa_nome' => $nomeDaMesa,
            'pessoa' => $premiado['pessoa'],
            'tipo' => $premiado['tipo'],
            'frase' => $premiado['frase'] ?? null,
            'concedido_em' => $data,
            'mesa_criada_em' => $criacao,
        ], $premiados);

        // Uma insercao para todos os empatados; a unicidade preserva as concessoes anteriores.
        DB::table('trofeus')->insertOrIgnore($linhas);
    }

    private function resultados(Builder $consulta): array
    {
        $linhas = $consulta->orderByRaw('COALESCE(mesa_criada_em, concedido_em) DESC')->orderByDesc('id')
            ->get(['id', 'pessoa', 'tipo', 'mesa_nome', 'frase', 'concedido_em', 'mesa_criada_em']);

        $trofeus = [];
        foreach ($linhas as $linha) {
            $trofeus[] = [
                'id' => (int) $linha->id,
                'pessoa' => $linha->pessoa,
                'tipo' => $linha->tipo,
                'mesa' => $linha->mesa_nome,
                'frase' => $linha->frase,
                'concedidoEm' => new DateTimeImmutable($linha->concedido_em, new DateTimeZone('UTC')),
                'mesaCriadaEm' => self::instante($linha->mesa_criada_em),
            ];
        }

        return $trofeus;
    }

    private static function emUtc(?DateTimeImmutable $data): ?string
    {
        return $data?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function instante(?string $valor): ?DateTimeImmutable
    {
        return $valor === null ? null : new DateTimeImmutable($valor, new DateTimeZone('UTC'));
    }
}
