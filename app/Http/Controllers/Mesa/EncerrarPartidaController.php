<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Mesa\NomeDaMesa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class EncerrarPartidaController
{
    use GaranteMembroDaMesa;

    public function formulario(Plataforma $plataforma, string $id): View|RedirectResponse
    {
        $mesa = $this->mesaDoAdmin($plataforma, $id);
        $partida = $mesa->partidaAtiva();
        if ($partida === null || $partida->situacao() !== SituacaoPartida::EmAndamento) {
            return redirect()->route('mesas.mostrar', $id);
        }

        return view('mesa.encerrar-partida', [
            'id' => $mesa->id,
            'nome' => NomeDaMesa::de($mesa),
        ]);
    }

    public function encerrar(Plataforma $plataforma, string $id): RedirectResponse
    {
        $this->mesaDoAdmin($plataforma, $id);
        /** @var User $usuario */
        $usuario = auth()->user();

        try {
            $plataforma->encerrarPartida($id, $usuario->identificadorNoDominio());
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withErrors(['partida' => $erro->getMessage()]);
        }

        return redirect()->route('mesas.mostrar', $id);
    }
}
