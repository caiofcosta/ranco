<?php

declare(strict_types=1);

namespace App\Http\Controllers\Amizade;

use App\Amizade\ConvitesDeAmizade;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Http\Controllers\Controller;
use App\Models\ConviteDeAmizade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tela de amigos: localizar conta por e-mail, convites recebidos e amizades. */
final class AmigosController extends Controller
{
    public function __construct(
        private readonly Plataforma $plataforma,
        private readonly ConvitesDeAmizade $convites,
    ) {}

    public function index(): View
    {
        $eu = $this->usuario();
        $amigos = User::query()
            ->whereIn('id', $this->plataforma->amigosDe($eu->identificadorNoDominio()))
            ->orderBy('name')
            ->get(['id', 'name']);
        $recebidos = $this->convites->recebidos($eu);

        return view('amigos.index', [
            'amigos' => $amigos,
            'recebidos' => $recebidos,
            'nomes' => User::query()->whereIn('id', $recebidos->pluck('de'))->pluck('name', 'id'),
        ]);
    }

    public function convidar(Request $request): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $email = mb_strtolower(trim($dados['email']));
        $eu = $this->usuario();

        try {
            $this->convites->enviar($eu, $email);
        } catch (RegraViolada $erro) {
            return back()->withInput()->withErrors(['email' => $erro->getMessage()]);
        }

        return back()->with('status', ConvitesDeAmizade::MENSAGEM_ENVIO);
    }

    public function aceitar(ConviteDeAmizade $convite): RedirectResponse
    {
        $eu = $this->usuario();
        if (! $this->convites->foiPara($convite, $eu)) {
            abort(404);
        }

        return $this->tentar(fn () => $this->convites->aceitar($convite, $eu), 'Amizade aceita.');
    }

    public function recusar(ConviteDeAmizade $convite): RedirectResponse
    {
        $eu = $this->usuario();
        if (! $this->convites->foiPara($convite, $eu)) {
            abort(404);
        }

        return $this->tentar(fn () => $this->convites->recusar($convite, $eu), 'Convite recusado.');
    }

    public function desfazer(string $amigo): RedirectResponse
    {
        $eu = $this->usuario()->identificadorNoDominio();

        return $this->tentar(fn () => $this->plataforma->desfazerAmizade($eu, $amigo), 'Amizade desfeita.');
    }

    /** Bloquear desfaz a amizade (se houver) e impede novos convites dessa pessoa. */
    public function bloquear(string $amigo): RedirectResponse
    {
        $eu = $this->usuario()->identificadorNoDominio();

        return $this->tentar(function () use ($eu, $amigo): void {
            if ($this->plataforma->saoAmigos($eu, $amigo)) {
                $this->plataforma->desfazerAmizade($eu, $amigo);
            }
            $this->plataforma->bloquear($eu, $amigo);
        }, 'Pessoa bloqueada.');
    }

    private function tentar(callable $acao, string $mensagem): RedirectResponse
    {
        try {
            $acao();
        } catch (RegraViolada $erro) {
            return redirect()->back(fallback: route('amigos.index'))->withErrors(['amizade' => $erro->getMessage()]);
        }

        // Aceitar e recusar também saem da Início; cada um volta para a tela de onde veio.
        return redirect()->back(fallback: route('amigos.index'))->with('status', $mensagem);
    }

    private function usuario(): User
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario;
    }
}
