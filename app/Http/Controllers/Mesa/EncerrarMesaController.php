<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Mesa\HistoricoDaMesa;
use App\Mesa\NomeDaMesa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class EncerrarMesaController
{
    use GaranteMembroDaMesa;

    public function formulario(Plataforma $plataforma, string $id): View
    {
        $mesa = $this->mesaDoAdmin($plataforma, $id);

        return view('mesa.encerrar', [
            'id' => $mesa->id,
            'nome' => NomeDaMesa::de($mesa),
        ]);
    }

    public function encerrar(Plataforma $plataforma, string $id): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $mesa = $this->mesaDoMembro($plataforma, $id);
        // Antes de encerrar: a mesa teve partida concluída de verdade? (O fechamento forçado da
        // partida ativa ainda não aconteceu, então o histórico só traz as partidas já concluídas.)
        $tinhaPartidaConcluida = HistoricoDaMesa::de($mesa)->partidas !== [];

        try {
            $plataforma->encerrarMesa($id, $usuario->identificadorNoDominio());
        } catch (RegraViolada) {
            return redirect()->route('dashboard');
        }

        // Cancelamento (nenhuma partida concluída): volta à lista, sem resultado nem troféus.
        if (! $tinhaPartidaConcluida) {
            return redirect()->route('dashboard')->with('status', 'Mesa cancelada. Dessa vez não rolou!');
        }

        // Finalização: segue para o resumo, que apura ranking e troféus como de hábito.
        return redirect()->route('mesas.mostrar', $id);
    }
}
