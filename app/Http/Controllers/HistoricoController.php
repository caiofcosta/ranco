<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class HistoricoController extends Controller
{
    public function __invoke(Request $pedido): Response
    {
        /** @var User $usuario */
        $usuario = $pedido->user();
        $identificador = $usuario->identificadorNoDominio();
        $visivel = ! (bool) $usuario->getAttribute('invisivel');

        // As projeções bastam para listar: não selecionar nem desserializar estado.
        $mesas = DB::table('mesas')
            ->leftJoin('membros_mesa as membro', function (JoinClause $join) use ($identificador): void {
                $join->on('membro.mesa_id', '=', 'mesas.id')
                    ->where('membro.usuario', $identificador);
            })
            ->leftJoin('users as administrador', 'administrador.id', '=', 'mesas.admin')
            ->where(function (Builder $consulta) use ($identificador): void {
                $consulta->whereNotNull('membro.usuario')
                    ->orWhereExists(function (Builder $local) use ($identificador): void {
                        $local->selectRaw('1')
                            ->from('participantes_locais')
                            ->whereColumn('participantes_locais.mesa_id', 'mesas.id')
                            ->where('participantes_locais.usuario', $identificador)
                            ->where('participantes_locais.situacao', 'reconhecida');
                    });
            })
            ->orderByDesc('mesas.atualizada_em')
            ->orderByDesc('mesas.id')
            ->paginate(10, [
                'mesas.id',
                'mesas.nome',
                'mesas.situacao',
                'administrador.name as nome_do_admin',
                'membro.situacao as situacao_da_conta',
            ])
            ->through(static fn ($mesa): array => [
                'id' => $mesa->id,
                'nome' => filled($mesa->nome)
                    ? $mesa->nome
                    : (filled($mesa->nome_do_admin) ? 'Mesa de '.$mesa->nome_do_admin : 'Mesa'),
                'situacao' => $mesa->situacao === 'encerrada' ? 'Encerrada' : 'Aberta',
                'participacao' => match ($mesa->situacao_da_conta) {
                    null => 'Participação local reconhecida',
                    'abandonada' => 'Você saiu desta mesa',
                    'removida' => 'Você foi removido desta mesa',
                    default => 'Participação na mesa',
                },
                // Espelha o acesso atual: reconhecimento local não autoriza abrir a mesa.
                'podeAbrir' => $mesa->situacao_da_conta !== null
                    && ($mesa->situacao === 'encerrada'
                        || ($mesa->situacao_da_conta === 'ativa' && $visivel)),
            ]);

        return response()->view('historico.index', [
            'mesas' => $mesas,
        ])->header('Cache-Control', 'no-store, private');
    }
}
