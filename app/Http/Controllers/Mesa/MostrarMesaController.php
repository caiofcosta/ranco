<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Conquista\RepositorioDeTrofeus;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Mesa\SituacaoNaMesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Mesa\HistoricoDaMesa;
use App\Mesa\NomeDaMesa;
use App\Mesa\PessoasDaMesa;
use App\Mesa\RankingDaMesa;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MostrarMesaController
{
    use GaranteMembroDaMesa;

    public function __invoke(Plataforma $plataforma, RepositorioDeTrofeus $repositorioDeTrofeus, string $id): View|RedirectResponse
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $mesa = $this->mesaParaVisualizar($plataforma, $id);
        PessoasDaMesa::preparar($mesa);

        if ($mesa->situacao() === SituacaoMesa::Encerrada) {
            $conquistas = $plataforma->trofeusDaMesa($mesa, $repositorioDeTrofeus);
            $identificador = $usuario->identificadorNoDominio();
            $rever = request()->query('rever') === '1';
            $pendente = ! $rever && $conquistas !== [] && in_array(
                $id, array_column($plataforma->resultadosPendentesDe($identificador), 'id'), true,
            );
            $pagina = $this->resumoDaMesa($mesa, $conquistas)
                ->with('id', $id)->with('animar', $conquistas !== [] && ($pendente || $rever));
            if ($pendente) {
                $plataforma->marcarResultadoVisto($identificador, $id);
            }

            return $pagina;
        }

        if ($mesa->partidaAtiva()?->situacao() === SituacaoPartida::EmAndamento) {
            // POSTs antigos ainda voltam pela mesa: a mensagem precisa chegar ao jogo.
            session()->keep(['errors', '_old_input']);

            return redirect()->route('mesas.jogo', $id);
        }

        return view('mesa.mostrar', [
            'id' => $id,
            'nome' => NomeDaMesa::de($mesa),
            'resultado' => request()->integer('resultado') ?: null,
        ]);
    }

    private function resumoDaMesa(Mesa $mesa, array $conquistas): View
    {
        $participantes = [];
        foreach ($mesa->membros() as $membro) {
            $identificador = $membro['usuario'];
            $participantes[] = [
                'nome' => PessoasDaMesa::nome($mesa, $identificador)
                    ?? 'Participante indisponível',
                'local' => $mesa->participanteLocal($identificador) !== null,
                'saida' => match ($membro['situacao']) {
                    SituacaoNaMesa::Ativa => null,
                    SituacaoNaMesa::Abandonada => 'Saiu da mesa',
                    SituacaoNaMesa::Removida => 'Removido da mesa',
                },
            ];
        }

        $fuso = new DateTimeZone('America/Sao_Paulo');
        $trofeus = [];
        foreach ($conquistas as $trofeu) {
            $trofeus[] = [
                'id' => $trofeu['id'],
                'nome' => PessoasDaMesa::nome($mesa, $trofeu['pessoa']) ?? 'Participante indisponível',
                'tipo' => $trofeu['tipo'],
                'mesa' => $trofeu['mesa'],
                'frase' => $trofeu['frase'],
                'mostradaEm' => $trofeu['mesaCriadaEm'] ?? $trofeu['concedidoEm'],
            ];
        }
        usort($trofeus, static fn (array $a, array $b): int => $b['mostradaEm'] <=> $a['mostradaEm'] ?: $b['id'] <=> $a['id']);
        $trofeus = array_map(static function (array $trofeu) use ($fuso): array {
            return [
                'nome' => $trofeu['nome'],
                'tipo' => $trofeu['tipo'],
                'mesa' => $trofeu['mesa'],
                'frase' => $trofeu['frase'],
                'data' => $trofeu['mostradaEm']->setTimezone($fuso)->format('d/m/Y'),
            ];
        }, $trofeus);

        return view('mesa.encerrada', [
            'nome' => NomeDaMesa::de($mesa),
            'ranking' => RankingDaMesa::de($mesa),
            'participantes' => $participantes,
            'partidas' => HistoricoDaMesa::de($mesa)->partidas,
            'trofeus' => $trofeus,
        ]);
    }
}
