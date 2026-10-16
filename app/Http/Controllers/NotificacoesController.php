<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DismissaoDeModal;
use App\Models\ModalDeNotificacao;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NotificacoesController extends Controller
{
    public function __invoke(Request $pedido): Response
    {
        /** @var User $usuario */
        $usuario = $pedido->user();

        $modais = ModalDeNotificacao::ativas()->latest()->get();

        $dismissoes = DismissaoDeModal::where('user_id', $usuario->id)
            ->whereIn('modal_de_notificacao_id', $modais->pluck('id'))
            ->get()
            ->keyBy('modal_de_notificacao_id');

        $itens = $modais->map(fn ($modal) => [
            'modal'  => $modal,
            'lida'   => $dismissoes->has($modal->id) && $dismissoes->get($modal->id)->visualizou_em !== null,
            'oculta' => $dismissoes->has($modal->id) && $dismissoes->get($modal->id)->nao_mostrar_novamente,
        ]);

        return response()->view('notificacoes.index', [
            'itens'    => $itens,
            'naoLidas' => $itens->where('lida', false)->count(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
