<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CadastrarParticipanteLocalController
{
    use GaranteMembroDaMesa;

    public function __invoke(Request $request, Plataforma $plataforma, string $id): RedirectResponse
    {
        $this->mesaDoMembro($plataforma, $id);
        /** @var User $usuario */
        $usuario = auth()->user();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            $plataforma->cadastrarParticipanteLocal(
                $id,
                $dados['nome'],
                $dados['email'],
                $usuario->identificadorNoDominio(),
            );
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withInput()->withErrors(['nome' => $erro->getMessage()]);
        }

        return redirect()->route('mesas.mostrar', $id);
    }
}
