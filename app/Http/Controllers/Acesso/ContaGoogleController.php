<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acesso;

use App\Actions\Acesso\EntrarComGoogle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as RedirecionamentoExterno;
use Throwable;

/** Entrar com Google (Socialite). Só existe quando o Client ID está configurado. */
final class ContaGoogleController
{
    public function redirecionar(): RedirecionamentoExterno
    {
        abort_unless(self::configurado(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function retorno(Request $request, EntrarComGoogle $entrar): RedirectResponse
    {
        abort_unless(self::configurado(), 404);

        try {
            $conta = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Não deu para entrar com o Google. Tente de novo.']);
        }

        try {
            $usuario = $entrar->executar($conta);
        } catch (ValidationException $erro) {
            return redirect()->route('login')->withErrors($erro->errors());
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public static function configurado(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
