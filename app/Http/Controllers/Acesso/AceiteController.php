<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acesso;

use App\Acesso\Aceites;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class AceiteController
{
    public function mostrar(Request $pedido, Aceites $aceites): Response|RedirectResponse
    {
        /** @var User $pessoa */
        $pessoa = $pedido->user();

        if ($aceites->aceitouVigente($pessoa)) {
            return redirect()->intended(route('dashboard'));
        }

        return response()->view('acesso.aceite', [
            'novaVersao' => $pessoa->aceites()->exists(),
            'versao' => $aceites->versaoVigente(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function registrar(Request $pedido, Aceites $aceites): RedirectResponse
    {
        $pedido->validate([
            'confirmacao' => ['required', 'accepted'],
            'versao' => ['required', Rule::in([$aceites->versaoVigente()])],
        ], [
            'confirmacao.required' => 'Para continuar, marque a confirmação.',
            'confirmacao.accepted' => 'Para continuar, marque a confirmação.',
            'versao.required' => 'As regras mudaram. Leia e aceite a nova versão para continuar.',
            'versao.in' => 'As regras mudaram. Leia e aceite a nova versão para continuar.',
        ]);

        /** @var User $pessoa */
        $pessoa = $pedido->user();
        $aceites->registrar($pessoa, (string) $pedido->ip());

        return redirect()->intended(route('dashboard'));
    }
}
