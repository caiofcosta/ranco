<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Convite\ConvidarParaMesa;
use App\Convite\MensagemDoConvite;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ConvidarAmigoController
{
    use GaranteMembroDaMesa;

    public function __invoke(
        Request $request,
        Plataforma $plataforma,
        ConvidarParaMesa $convidar,
        string $id,
    ): RedirectResponse {
        $this->mesaDoAdmin($plataforma, $id);
        /** @var User $admin */
        $admin = auth()->user();

        $dados = $request->validate(['amigo' => ['required', 'string']]);
        $convidado = User::query()->find($dados['amigo']);
        if ($convidado === null) {
            abort(404);
        }

        try {
            $convidar->executar($id, $admin, $convidado);
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withErrors(['convite' => MensagemDoConvite::de($erro)]);
        }

        return redirect()->route('mesas.mostrar', $id)->with('status', 'Convite enviado.');
    }
}
