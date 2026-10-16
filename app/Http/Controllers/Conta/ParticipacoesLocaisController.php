<?php

declare(strict_types=1);

namespace App\Http\Controllers\Conta;

use App\Conta\ParticipacaoLocalNaConta;
use App\Dominio\NaoEncontrado;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Lista as participações locais pendentes do e-mail da conta e registra o reconhecimento ou a rejeição. */
final class ParticipacoesLocaisController
{
    public function index(Plataforma $plataforma): View
    {
        $usuario = $this->usuario();

        return view('conta.participacoes-locais', [
            'pendentes' => ParticipacaoLocalNaConta::pendentes($plataforma, $usuario->email),
        ]);
    }

    public function reconhecer(Plataforma $plataforma, string $participacao): RedirectResponse
    {
        $usuario = $this->usuario();

        try {
            $plataforma->reconhecerParticipacaoLocal($participacao, $usuario->identificadorNoDominio(), $usuario->email);
        } catch (RegraViolada $erro) {
            return $this->voltarComErro($erro);
        } catch (NaoEncontrado) {
            abort(404);
        }

        return $this->voltar('Participação reconhecida.');
    }

    public function rejeitar(Plataforma $plataforma, string $participacao): RedirectResponse
    {
        $usuario = $this->usuario();

        try {
            $plataforma->rejeitarParticipacaoLocal($participacao, $usuario->email);
        } catch (RegraViolada $erro) {
            return $this->voltarComErro($erro);
        } catch (NaoEncontrado) {
            abort(404);
        }

        return $this->voltar('Participação rejeitada.');
    }

    private function voltar(string $status): RedirectResponse
    {
        return redirect()->route('conta.participacoes-locais')->with('status', $status);
    }

    private function voltarComErro(RegraViolada $erro): RedirectResponse
    {
        return redirect()->route('conta.participacoes-locais')->withErrors(['participacao' => $erro->getMessage()]);
    }

    private function usuario(): User
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario;
    }
}
