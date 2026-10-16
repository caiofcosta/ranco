<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jogos;

use App\Dominio\Plataforma\Plataforma;
use App\Http\Controllers\Mesa\GaranteMembroDaMesa;
use App\Mesa\NomeDaMesa;
use App\Mesa\TelaDoJogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MostrarJogoController
{
    use GaranteMembroDaMesa;

    public function __invoke(Request $pedido, Plataforma $plataforma, string $id): Response|RedirectResponse
    {
        $mesa = $this->mesaDoMembro($plataforma, $id);
        $numero = $pedido->integer('partida', count($mesa->partidas()));
        $tela = TelaDoJogo::de($mesa, $pedido->user()->identificadorNoDominio(), $numero);
        if ($tela === null) {
            return redirect()->route('mesas.mostrar', $id);
        }
        // O resultado abre na mesa, em modal que só o jogador fecha.
        if ($tela->fase === 'fim') {
            return redirect()->route('mesas.mostrar', ['id' => $id, 'resultado' => $numero]);
        }
        if (in_array($tela->fase, ['iniciar', 'jogar'], true)) {
            return app(JogarVezController::class)->mostrar($pedido, $plataforma, $id);
        }
        if ($tela->fase === 'assistir') {
            return app(AssistirVezController::class)($pedido, $plataforma, $id);
        }

        return response()->view('jogos.partida.acompanhar', [
            'id' => $id, 'telaDoJogo' => $tela, 'nomeDaMesa' => NomeDaMesa::de($mesa),
        ])->header('Cache-Control', 'no-store, private');
    }
}
