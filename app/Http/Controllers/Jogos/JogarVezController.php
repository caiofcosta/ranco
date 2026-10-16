<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jogos;

use App\Dominio\ConflitoDeConcorrencia;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Mesa\Mesa;
use App\Dominio\NaoEncontrado;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Http\Controllers\Mesa\GaranteMembroDaMesa;
use App\Mesa\LacreDoTempo;
use App\Mesa\NomeDaMesa;
use App\Mesa\PessoasDaMesa;
use App\Mesa\TelaDoJogo;
use App\Mesa\TransmissaoDaVez;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use JsonException;

final class JogarVezController
{
    use GaranteMembroDaMesa;

    // Faixa de transporte do mapa atual (520 × 520, velocidade máxima de 120 px/s).
    // Não depende do resultado solo e não substitui as regras da partida.
    private const TEMPO_MINIMO = 4000;

    private const TEMPO_MAXIMO = 86400000;

    public function mostrar(Request $pedido, Plataforma $plataforma, string $id): Response|RedirectResponse
    {
        try {
            $mesa = $this->mesaDoMembro($plataforma, $id);
            $usuario = $pedido->user()->identificadorNoDominio();
            $partida = $this->partidaDisponivel($mesa, $usuario, aceitaPausada: true);
        } catch (RegraViolada|ConflitoDeConcorrencia) {
            return redirect()->route('mesas.mostrar', $id);
        }

        $participante = $this->participanteDaJogada($mesa, $usuario, $partida);
        $tentativa = $partida->tentativaDe($partida->rodadaAtual(), $participante, $partida->desempateAtual());
        $emAndamento = $tentativa?->situacao === SituacaoTentativa::EmAndamento;
        // Na pausa, só reabre o tabuleiro de quem já estava jogando; ninguém começa.
        if (($tentativa !== null && ! $emAndamento) || ($partida->pausada() && ! $emAndamento)) {
            return redirect()->route('mesas.mostrar', $id);
        }

        $view = $tentativa === null ? 'jogos.partida.iniciar' : 'jogos.partida.jogar';

        return response()->view($view, [
            'id' => $id,
            'mapaDaRodada' => $partida->mapaLabirinto(),
            'telaDoJogo' => TelaDoJogo::de($mesa, $pedido->user()->identificadorNoDominio()),
            'nomeDaMesa' => NomeDaMesa::de($mesa),
            'nomeDoParticipante' => PessoasDaMesa::nome($mesa, $participante),
            'participante' => $participante,
            'transmissao' => TransmissaoDaVez::contexto($mesa),
            'participanteLocal' => $mesa->participanteLocal($participante) !== null,
            'jogada' => Crypt::encryptString(json_encode([
                'mesa' => $id,
                'usuario' => $usuario,
                'participante' => $participante,
                'partida' => count($mesa->partidas()),
                'rodada' => $partida->rodadaAtual(),
                'desempate' => $partida->desempateAtual(),
            ], JSON_THROW_ON_ERROR)),
            'partida' => count($mesa->partidas()),
            'rodada' => $partida->rodadaAtual(),
            'desempate' => $partida->desempateAtual(),
            'iniciada' => $emAndamento,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function iniciar(Request $pedido, Plataforma $plataforma, string $id): RedirectResponse
    {
        $usuario = $pedido->user()->identificadorNoDominio();

        try {
            $this->mesaDoMembro($plataforma, $id);
            $dados = $pedido->validate($this->regrasDoContexto(), $this->mensagensDoContexto());
            // A Plataforma aplica a regra de uma jogada por vez; aqui só se confere o pedido.
            $plataforma->iniciarTentativa($id, $usuario, function (Mesa $mesa) use ($usuario, $dados): array {
                // A autorização é conferida novamente dentro da transação da mutação.
                abort_unless($mesa->membroAtivo($usuario), 404);
                $esperado = $this->participanteDoPedido($mesa, $usuario, $dados);

                return [$this->partidaDoPedido($mesa, $usuario, $dados, $esperado), $esperado];
            });
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withErrors(['vez' => $erro->getMessage()]);
        } catch (ConflitoDeConcorrencia) {
            return redirect()->route('mesas.jogar', $id)->withErrors(['vez' => 'A mesa mudou durante o pedido. Confira sua vez e tente novamente.']);
        } catch (NaoEncontrado) {
            abort(404);
        }

        return redirect()->route('mesas.jogar', $id);
    }

    public function resultado(Request $pedido, Plataforma $plataforma, string $id): JsonResponse
    {
        $usuario = $pedido->user()->identificadorNoDominio();

        try {
            // Lê a mesa sem avançar prazos: um resultado que chega logo após o fim do prazo ainda
            // precisa poder ser gravado antes que processarPrazos interrompa a tentativa.
            $mesaAtual = $this->mesaDoMembroSemAvancarPrazo($plataforma, $id);
            $numero = $pedido->input('partida');
            $rodada = $pedido->input('rodada');
            $mapa = is_numeric($numero) && is_numeric($rodada)
                ? ($mesaAtual->partidas()[(int) $numero - 1] ?? null)?->mapaLabirinto((int) $rodada) : null;
            $minimo = $mapa === null ? self::TEMPO_MINIMO : DesenhoLabirinto::tempoMinimo($mapa['definicao']);
            $dados = $pedido->validate([
                ...$this->regrasDoContexto(),
                'respostas' => ['nullable', 'array', 'max:100000'],
                'respostas.*' => ['integer', 'min:1', 'max:4'],
                'tempo' => ['required', 'integer', 'min:'.$minimo, 'max:'.self::TEMPO_MAXIMO],
            ], [
                ...$this->mensagensDoContexto(),
                'tempo.*' => 'Informe um tempo válido para o Labirinto, em milissegundos.',
            ]);
            // O tempo medido na tela vale como enviado; o lacre só barra quem troca o número no envio.
            if (! LacreDoTempo::confere($dados['jogada'], (int) $dados['tempo'], $pedido->input('lacre'))) {
                return response()->json(['mensagem' => 'Esta jogada não pôde ser validada. Volte para a mesa.', 'pode_reenviar' => false], 422);
            }
            $plataforma->naMesa($id, function (Mesa $mesa) use ($usuario, $dados): void {
                abort_unless($mesa->membroAtivo($usuario), 404);
                $esperado = $this->participanteDoPedido($mesa, $usuario, $dados);
                // O comprovante identifica a tentativa mesmo após avançar para outro local na mesma sessão.
                $original = $mesa->partidas()[(int) $dados['partida'] - 1] ?? null;
                $gravada = $original?->tentativaDe((int) $dados['rodada'], $esperado, (int) $dados['desempate']);
                $resultado = (int) $dados['tempo'];
                if ($gravada?->situacao === SituacaoTentativa::Concluida) {
                    if ($gravada->resultado !== $resultado) {
                        throw new RegraViolada('Esta tentativa já foi concluída com outro resultado. Volte para a mesa.');
                    }

                    return;
                }

                // Tentativa já iniciada termina mesmo com a partida pausada.
                $partida = $this->partidaDoPedido($mesa, $usuario, $dados, $esperado, aceitaPausada: true);
                if ($partida->tentativaDe($partida->rodadaAtual(), $esperado, $partida->desempateAtual()) === null) {
                    throw new RegraViolada('Sua tentativa ainda não começou. Volte para a mesa e inicie sua vez.');
                }
                $partida->concluirTentativa($esperado, $resultado);
            }, antesDoPrazo: true);
        } catch (RegraViolada $erro) {
            return response()->json(['mensagem' => $erro->getMessage(), 'pode_reenviar' => false], 409);
        } catch (ConflitoDeConcorrencia) {
            return response()->json(['mensagem' => 'A mesa mudou durante o envio. Tente enviar seu tempo novamente.', 'pode_reenviar' => true], 409);
        } catch (NaoEncontrado) {
            abort(404);
        }

        return response()->json(['destino' => route('mesas.jogo', ['id' => $id, 'partida' => (int) $dados['partida']])]);
    }

    /** Pausada só serve para ver ou terminar uma tentativa já iniciada; quem chama confere isso. */
    private function partidaDisponivel(Mesa $mesa, string $usuario, bool $aceitaPausada = false): Partida
    {
        $partida = $mesa->partidaAtiva();
        if ($partida === null || $partida->situacao() !== SituacaoPartida::EmAndamento) {
            throw new RegraViolada('Não há partida em andamento nesta mesa.');
        }
        if ($partida->pausada() && ! $aceitaPausada) {
            throw new RegraViolada('A partida está pausada. Volte para a mesa para acompanhar.');
        }
        if ($this->participanteDaJogada($mesa, $usuario, $partida) === null) {
            throw new RegraViolada('Sua vez não está mais disponível. Volte para a mesa para acompanhar.');
        }
        if ($partida->configuracao()->jogo !== CatalogoDeJogos::LABIRINTO) {
            throw new RegraViolada('O jogo desta partida não está disponível nesta tela.');
        }

        return $partida;
    }

    /** @param array<string, mixed> $dados */
    private function partidaDoPedido(Mesa $mesa, string $usuario, array $dados, string $esperado, bool $aceitaPausada = false): Partida
    {
        $partida = $this->partidaDisponivel($mesa, $usuario, $aceitaPausada);
        if ((int) $dados['partida'] !== count($mesa->partidas())
            || (int) $dados['rodada'] !== $partida->rodadaAtual()
            || (int) $dados['desempate'] !== $partida->desempateAtual()
            || $esperado !== $this->participanteDaJogada($mesa, $usuario, $partida)) {
            throw new RegraViolada('Esta tela pertence a uma vez anterior. Volte para a mesa para acompanhar.');
        }

        return $partida;
    }

    /** A regra de por quem se joga fica em `Mesa::porQuemJoga`. */
    private function participanteDaJogada(Mesa $mesa, string $usuario, Partida $partida): ?string
    {
        return $mesa->partidaAtiva() === $partida ? $mesa->porQuemJoga($usuario) : null;
    }

    /**
     * O navegador devolve um comprovante emitido no GET; ele não escolhe por quem jogar.
     *
     * @param  array<string, mixed>  $dados
     */
    private function participanteDoPedido(Mesa $mesa, string $usuario, array $dados): string
    {
        try {
            $jogada = json_decode(Crypt::decryptString($dados['jogada']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new RegraViolada('Esta jogada não é válida. Volte para a mesa e abra a vez novamente.');
        }

        if (! is_array($jogada)
            || ($jogada['mesa'] ?? null) !== $mesa->id
            || ($jogada['usuario'] ?? null) !== $usuario
            || ($jogada['partida'] ?? null) !== (int) $dados['partida']
            || ($jogada['rodada'] ?? null) !== (int) $dados['rodada']
            || ($jogada['desempate'] ?? null) !== (int) $dados['desempate']
            || ! is_string($jogada['participante'] ?? null)
            || ! $mesa->podeJogarPor($usuario, $jogada['participante'])) {
            throw new RegraViolada('Esta jogada não corresponde à sua vez. Volte para a mesa.');
        }

        return $jogada['participante'];
    }

    /** @return array<string, list<string>> */
    private function regrasDoContexto(): array
    {
        return [
            'jogada' => ['required', 'string', 'max:4096'],
            'partida' => ['required', 'integer', 'min:1'],
            'rodada' => ['required', 'integer', 'min:1'],
            'desempate' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    private function mensagensDoContexto(): array
    {
        return [
            'jogada.*' => 'Volte para a mesa e abra sua vez novamente.',
            'partida.*' => 'Volte para a mesa e abra sua vez novamente.',
            'rodada.*' => 'Volte para a mesa e abra sua vez novamente.',
            'desempate.*' => 'Volte para a mesa e abra sua vez novamente.',
        ];
    }
}
