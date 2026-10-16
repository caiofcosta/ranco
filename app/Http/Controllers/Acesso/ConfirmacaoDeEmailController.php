<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acesso;

use App\Acesso\LinksDeEntrada;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Link de confirmação do cadastro: confirma o e-mail e já entra, em qualquer aparelho. A senha
 * escolhida no cadastro continua valendo.
 */
final class ConfirmacaoDeEmailController
{
    public function confirmar(Request $request, LinksDeEntrada $links, User $usuario, string $codigo): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Este link de confirmação venceu ou não é válido. Entre com seu e-mail e senha para receber outro.',
            ]);
        }

        if (! $links->usar($codigo)) {
            return redirect()->route('login')->withErrors(['email' => 'Este link já foi usado. Entre com seu e-mail e senha.']);
        }

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
            event(new Verified($usuario));
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
