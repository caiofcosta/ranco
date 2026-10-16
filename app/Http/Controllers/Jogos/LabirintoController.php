<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jogos;

use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Jogos\Labirinto\FalhaSolo;
use App\Dominio\Jogos\Labirinto\ImportacaoMapaLabirinto;
use App\Dominio\Jogos\Labirinto\SoloLabirinto;
use App\Dominio\Jogos\Labirinto\TentativaSolo;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocador;
use App\Dominio\RegraViolada;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LabirintoController
{
    public function escolher(Request $pedido, SoloLabirinto $solo): View
    {
        return view('jogos.labirinto.escolher', ['mapas' => $solo->disponiveis((int) $pedido->user()->id)]);
    }

    public function iniciar(Request $pedido, SoloLabirinto $solo): RedirectResponse
    {
        $dados = $pedido->validate(['mapa' => ['nullable', 'integer', 'min:1']]);
        try {
            $id = $solo->iniciar((int) $pedido->user()->id, isset($dados['mapa']) ? (int) $dados['mapa'] : null);
        } catch (FalhaSolo $erro) {
            throw ValidationException::withMessages(['mapa' => $erro->getMessage()]);
        }

        return redirect()->route('jogar.labirinto.tentativa', $id);
    }

    public function jogar(Request $pedido, string $tentativa, SoloLabirinto $solo): View|RedirectResponse
    {
        $jogo = $solo->buscar((int) $pedido->user()->id, $tentativa);
        if ($jogo->tempo !== null) {
            return redirect()->route('jogar.labirinto.resultado', ['tentativa' => $tentativa]);
        }

        return view('jogos.labirinto.jogar', ['jogo' => $jogo, 'previa' => false]);
    }

    public function salvar(Request $pedido, SoloLabirinto $solo): JsonResponse
    {
        $dados = $pedido->validate([
            'tentativa' => ['required', 'uuid'],
            'tempo' => ['required', 'integer', 'min:1', 'max:'.(TentativaSolo::VALIDADE_SEGUNDOS * 1000)],
        ]);
        try {
            $solo->concluir((int) $pedido->user()->id, $dados['tentativa'], (int) $dados['tempo']);
        } catch (FalhaSolo $erro) {
            abort_if($erro->motivo === 'conflito', 409, $erro->getMessage());
            throw ValidationException::withMessages(['tempo' => $erro->getMessage()]);
        }

        return response()->json(['destino' => route('jogar.labirinto.resultado', ['tentativa' => $dados['tentativa']])]);
    }

    public function resultado(Request $pedido, SoloLabirinto $solo, Provocador $provocador): View|RedirectResponse
    {
        $dados = $pedido->validate(['tentativa' => ['nullable', 'uuid']]);
        if (empty($dados['tentativa'])) {
            return redirect()->route('jogar.labirinto');
        }
        $jogo = $solo->buscar((int) $pedido->user()->id, $dados['tentativa']);
        if ($jogo->tempo === null) {
            return redirect()->route('jogar.labirinto.tentativa', $jogo->id);
        }

        return view('jogos.labirinto.resultado', [
            'jogo' => $jogo, 'tempo' => (int) $jogo->tempo, 'recorde' => (bool) $jogo->recorde,
            'frase' => $provocador->sortear($jogo->recorde ? Momento::SoloMelhorTempo : Momento::SoloSemRecorde),
            ...$solo->historico((int) $pedido->user()->id, (int) $jogo->versao_id),
        ]);
    }

    public function previa(Request $pedido): View
    {
        abort_unless($pedido->user()->canAccessPanel(Filament::getPanel('painel')), 403);
        $dados = $pedido->validate(['mapa' => ['required', 'string', 'max:8192']]);
        try {
            $mapa = ImportacaoMapaLabirinto::ler($dados['mapa']);
        } catch (RegraViolada $erro) {
            throw ValidationException::withMessages(['mapa' => $erro->getMessage()]);
        }
        $jogo = (object) ['nome' => $mapa['nome'], 'desenho' => json_encode(DesenhoLabirinto::definicao($mapa['linhas']), JSON_THROW_ON_ERROR)];

        return view('jogos.labirinto.jogar', ['jogo' => $jogo, 'previa' => true]);
    }
}
