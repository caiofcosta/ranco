<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ModalDeNotificacao;
use App\Servicos\RepositorioDeModaisDeNotificacao;
use Illuminate\Http\Request;

class ModalDeNotificacaoController extends Controller
{
    public function __construct(private RepositorioDeModaisDeNotificacao $repositorio)
    {
    }

    public function visualizar(Request $request)
    {
        $request->validate([
            'modal_id' => 'required|exists:modais_de_notificacao,id',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        $modal = ModalDeNotificacao::findOrFail($request->modal_id);
        $this->repositorio->marcarComoVisualizada($user, $modal);

        return response()->json(['success' => true]);
    }

    public function dismissar(Request $request)
    {
        $request->validate([
            'modal_id' => 'required|exists:modais_de_notificacao,id',
            'nao_mostrar_novamente' => 'required|boolean',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        $modal = ModalDeNotificacao::findOrFail($request->modal_id);
        $this->repositorio->marcarComoDescartada($user, $modal, $request->nao_mostrar_novamente);

        return response()->json(['success' => true]);
    }
}
