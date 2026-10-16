<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acesso;

use App\Acesso\LinksDeEntrada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Entrar sem senha: um link assinado, válido por 30 minutos e de uso único, enviado por e-mail.
 * O pedido responde igual para e-mails com e sem conta.
 */
final class AcessoPorLinkController
{
    public const MENSAGEM_ENVIADO = 'Link enviado. Se existir uma conta com esse e-mail, ele chega em instantes, vale por 30 minutos e funciona uma vez só.';

    public function formulario(): View
    {
        return view('pages::auth.acesso-por-link');
    }

    public function enviar(Request $request, LinksDeEntrada $links): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'string', 'email']]);

        $usuario = User::where('email', mb_strtolower(trim($dados['email'])))->first();
        if ($usuario !== null && $usuario->bloqueado_em === null) {
            $links->enviarAcesso($usuario);
        }

        return back()->with('status', self::MENSAGEM_ENVIADO);
    }

    /**
     * Abrir o link prova a posse do e-mail: confirma o e-mail e entra. Se a conta ainda não estava
     * confirmada, a senha é descartada, porque pode ter sido definida por outra pessoa que cadastrou
     * este e-mail antes.
     */
    public function entrar(Request $request, LinksDeEntrada $links, User $usuario, string $codigo): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors(['email' => 'Este link venceu ou não é válido. Peça outro.']);
        }

        if ($usuario->bloqueado_em !== null) {
            return redirect()->route('login')->withErrors(['email' => 'Este link venceu ou não é válido. Peça outro.']);
        }

        if (! $links->usar($codigo)) {
            return redirect()->route('login')->withErrors(['email' => 'Este link já foi usado. Peça outro.']);
        }

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->password = null;
            $usuario->email_verified_at = now();
            $usuario->save();
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
