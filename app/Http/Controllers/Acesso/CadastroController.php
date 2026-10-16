<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acesso;

use App\Acesso\LinksDeEntrada;
use App\Actions\Acesso\Cadastrar;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cadastro por e-mail e senha. A resposta é sempre a tela "Confira seu e-mail", sem entrar na conta:
 * quem entra é o link do e-mail. Assim a tela não revela se o e-mail já tinha conta.
 */
final class CadastroController
{
    /** E-mail do cadastro, guardado na sessão para a tela "Confira seu e-mail" e para o reenvio. */
    public const EMAIL_NA_SESSAO = 'cadastro.email';

    private const CONTA_NOVA_NA_SESSAO = 'cadastro.conta_nova';

    public function formulario(): View
    {
        return view('pages::auth.register');
    }

    public function cadastrar(Request $request, Cadastrar $cadastrar): RedirectResponse
    {
        $contaNova = $cadastrar->executar($request->all());

        $request->session()->put(self::EMAIL_NA_SESSAO, mb_strtolower(trim((string) $request->input('email'))));
        $request->session()->put(self::CONTA_NOVA_NA_SESSAO, $contaNova);

        return redirect()->route('cadastro.confira-seu-email');
    }

    public function confiraSeuEmail(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL_NA_SESSAO);
        if (! is_string($email)) {
            return redirect()->route('register');
        }

        return view('pages::auth.confira-seu-email', ['email' => $email]);
    }

    /** Repete o link do cadastro: confirmação para a conta criada agora; acesso nos outros casos. */
    public function reenviarLink(Request $request, LinksDeEntrada $links): RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL_NA_SESSAO);
        if (! is_string($email)) {
            return redirect()->route('register');
        }

        $usuario = User::where('email', $email)->first();
        if ($usuario !== null) {
            $contaNova = $request->session()->get(self::CONTA_NOVA_NA_SESSAO) === true;

            if ($contaNova && ! $usuario->hasVerifiedEmail()) {
                $links->enviarConfirmacao($usuario);
            } else {
                $links->enviarAcesso($usuario, pediuCadastro: true);
            }
        }

        return back()->with('status', 'Enviamos outro link.');
    }
}
