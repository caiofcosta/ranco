<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jogos;

use App\Dominio\Plataforma\Plataforma;
use App\Http\Controllers\Mesa\GaranteMembroDaMesa;
use App\Mesa\NomeDaMesa;
use App\Mesa\PessoasDaMesa;
use App\Mesa\TelaDoJogo;
use App\Mesa\TransmissaoDaVez;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AssistirVezController
{
    use GaranteMembroDaMesa;

    public function __invoke(Request $pedido, Plataforma $plataforma, string $id): Response|RedirectResponse
    {
        $mesa = $this->mesaDoMembro($plataforma, $id);
        $contexto = TransmissaoDaVez::contexto($mesa);
        if ($contexto === null || $mesa->podeJogarPor($pedido->user()->identificadorNoDominio(), $contexto['participante'])) {
            return redirect()->route('mesas.mostrar', $id);
        }

        $partida = $mesa->partidaAtiva();

        return response()->view('jogos.partida.assistir', [
            'id' => $id,
            'mapaDaRodada' => $partida?->mapaLabirinto(),
            'telaDoJogo' => TelaDoJogo::de($mesa, $pedido->user()->identificadorNoDominio()),
            'contexto' => $contexto,
            'nomeDaMesa' => NomeDaMesa::de($mesa),
            'nomeDoParticipante' => PessoasDaMesa::nome($mesa, $contexto['participante']),
        ])->header('Cache-Control', 'no-store, private');
    }
}
