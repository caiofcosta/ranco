<?php

declare(strict_types=1);

namespace App\Livewire\Mesa;

use App\Dominio\ConflitoDeConcorrencia;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Http\Controllers\Mesa\GaranteMembroDaMesa;
use App\Mesa\RankingDaMesa;
use App\Mesa\TelaDoJogo;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Atualiza os dados ao lado do canvas sem remontar o jogo em andamento. */
final class PainelDoJogo extends Component
{
    use GaranteMembroDaMesa;

    #[Locked]
    public string $id;

    #[Locked]
    public int $numero;

    #[Locked]
    public string $chave;

    #[Locked]
    public bool $compacta = false;

    public function boot(): void
    {
        abort_unless(auth()->user() instanceof User, 401);
        abort_unless(auth()->user()->hasVerifiedEmail(), 403);
    }

    public function mount(string $id, int $numero, string $chave, bool $compacta = false): void
    {
        $this->id = $id;
        $this->numero = $numero;
        $this->chave = $chave;
        $this->compacta = $compacta;
    }

    #[On('mesa-atualizada.{id}')]
    public function atualizarMesa(): void {}

    public function pausarPartida(): void
    {
        $this->executar('pausarPartida');
    }

    public function despausarPartida(): void
    {
        $this->executar('despausarPartida');
    }

    public function encerrarPartida(): void
    {
        $this->executar('encerrarPartida');
    }

    public function pularVez(string $participante): void
    {
        $this->executar('pularVez', $participante);
    }

    public function admitirNaPartida(string $participante): void
    {
        $this->executar('admitirNaPartida', $participante);
    }

    public function removerDaPartida(string $participante): void
    {
        $this->executar('removerDaPartida', $participante);
    }

    public function iniciarProximaRodada(): void
    {
        $this->executar('iniciarProximaRodada');
    }

    private function executar(string $acao, ?string $participante = null): void
    {
        $plataforma = app(Plataforma::class);
        $mesa = $this->mesaDoAdmin($plataforma, $this->id);
        $this->resetErrorBag();
        if (count($mesa->partidas()) !== $this->numero || $mesa->partidaAtiva() === null) {
            $this->addError('partida', 'Esta partida já terminou. Volte à mesa.');

            return;
        }
        try {
            $por = auth()->user()->identificadorNoDominio();
            if ($participante === null) {
                $plataforma->{$acao}($this->id, $por);
            } else {
                $plataforma->{$acao}($this->id, $participante, $por);
            }
        } catch (RegraViolada $erro) {
            $this->addError('partida', $erro->getMessage());
        } catch (ConflitoDeConcorrencia) {
            $this->addError('partida', 'A mesa foi atualizada. Confira o estado atual e tente novamente.');
        }
    }

    public function render(): View
    {
        $plataforma = app(Plataforma::class);
        $usuario = auth()->user()->identificadorNoDominio();
        if ($plataforma->estaInvisivel($usuario)) {
            $this->redirectRoute('dashboard');

            return view('livewire.mesa.painel-do-jogo', ['tela' => null]);
        }
        try {
            $mesa = $this->mesaParaVisualizar($plataforma, $this->id);
        } catch (NotFoundHttpException) {
            $this->redirectRoute('dashboard');

            return view('livewire.mesa.painel-do-jogo', ['tela' => null]);
        }
        if ($mesa->situacao() === SituacaoMesa::Encerrada) {
            $this->redirectRoute('mesas.mostrar', $this->id);

            return view('livewire.mesa.painel-do-jogo', ['tela' => null]);
        }
        $tela = TelaDoJogo::de($mesa, $usuario, $this->numero);
        if ($tela === null) {
            $this->redirectRoute('mesas.mostrar', $this->id);
        } elseif ($tela->chave !== $this->chave) {
            // Quando o jogador está na fase 'jogar', o JS de entrada-partida.js trata a navegação
            // via window.location.assign após receber a resposta do AJAX. Emitir o redirect aqui
            // também causaria dupla navegação e piscada. Para observadores (outras fases), o
            // Livewire navega de forma suave via wire:navigate.
            $chaveAtual = json_decode($this->chave, true);
            if (($chaveAtual[1] ?? null) !== 'jogar') {
                $this->redirectRoute('mesas.jogo', ['id' => $this->id, 'partida' => $this->numero], navigate: true);
            }
        }

        return view('livewire.mesa.painel-do-jogo', [
            'tela' => $tela, 'ranking' => RankingDaMesa::de($mesa),
        ]);
    }
}
