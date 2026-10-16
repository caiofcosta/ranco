<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Conquista\RepositorioDeRevelacoes;
use App\Dominio\Plataforma\RepositorioDeMesas;
use App\Mesa\NomeDaMesa;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class RepositorioDeRevelacoesNoBanco implements RepositorioDeRevelacoes
{
    public function __construct(private readonly RepositorioDeMesas $mesas) {}

    public function pendentesDe(string $usuario): array
    {
        // O encerramento permanece estavel mesmo se uma participacao local for reconhecida depois.
        $ids = $this->mesasDaConta($usuario)
            ->whereExists(fn (Builder $consulta) => $consulta->selectRaw('1')->from('trofeus')
                ->whereColumn('trofeus.mesa_id', 'mesas.id'))
            ->whereNotExists(fn (Builder $consulta) => $consulta->selectRaw('1')->from('revelacoes_vistas')
                ->whereColumn('revelacoes_vistas.mesa_id', 'mesas.id')->where('revelacoes_vistas.usuario', $usuario))
            ->orderByDesc('mesas.encerrada_em')->orderByDesc('mesas.id')->pluck('mesas.id');

        $resultados = [];
        foreach ($ids as $id) {
            $mesa = $this->mesas->buscar($id);
            if ($mesa !== null) {
                $resultados[] = ['id' => $id, 'nome' => NomeDaMesa::de($mesa)];
            }
        }

        return $resultados;
    }

    public function marcarVista(string $usuario, string $mesaId, DateTimeImmutable $quando): void
    {
        if (! $this->mesasDaConta($usuario)->where('mesas.id', $mesaId)->exists()) {
            return;
        }

        DB::table('revelacoes_vistas')->insertOrIgnore([
            'usuario' => $usuario,
            'mesa_id' => $mesaId,
            'visto_em' => $quando->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function mesasDaConta(string $usuario): Builder
    {
        return DB::table('mesas')->join('membros_mesa', 'membros_mesa.mesa_id', '=', 'mesas.id')
            ->where('membros_mesa.usuario', $usuario)->where('mesas.situacao', 'encerrada')
            ->whereNotExists(fn (Builder $consulta) => $consulta->selectRaw('1')->from('participantes_locais')
                ->whereColumn('participantes_locais.mesa_id', 'mesas.id')
                ->whereColumn('participantes_locais.id', 'membros_mesa.usuario'));
    }
}
