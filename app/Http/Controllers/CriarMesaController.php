<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Valida o nome obrigatório e cria a mesa. */
final class CriarMesaController extends Controller
{
    public function __invoke(Request $request, Plataforma $plataforma): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:40'],
        ], [
            'nome.required' => 'Informe o nome da mesa.',
        ]);

        try {
            $mesa = $plataforma->criarMesa($usuario->identificadorNoDominio(), trim($dados['nome']));
        } catch (RegraViolada $erro) {
            return redirect()->route('dashboard')->withErrors(['mesa' => $erro->getMessage()]);
        }

        return redirect()->route('mesas.mostrar', $mesa->id);
    }
}
