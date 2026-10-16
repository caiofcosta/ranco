<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Acesso\Aceites;
use App\Models\User;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExigeAceiteDasRegras
{
    private const ROTAS_LIBERADAS = [
        'home', 'aceite.mostrar', 'aceite.registrar',
        'documentos.termos', 'documentos.privacidade', 'documentos.conduta',
        'conta.excluir', 'logout', 'login', 'login.store', 'register', 'register.store',
        'cadastro.confira-seu-email', 'cadastro.reenviar-link',
        'acesso-por-link', 'acesso-por-link.enviar', 'acesso-por-link.entrar',
        'google.redirecionar', 'google.retorno', 'confirmacao-de-email',
        'verification.notice', 'verification.verify', 'verification.send',
        'password.request', 'password.email', 'password.reset', 'password.update',
        'password.confirm', 'password.confirm.store', 'password.confirmation',
        'two-factor.login', 'two-factor.login.store',
        'amizade.convite', 'amizade.aceitar', 'painel.labirinto.previa',
    ];

    public function __construct(private readonly Aceites $aceites) {}

    public function handle(Request $pedido, Closure $proximo): Response
    {
        $pessoa = $pedido->user();

        if (! $pessoa instanceof User) {
            return $proximo($pedido);
        }

        // O Livewire verifica o snapshot antes de reaplicar o middleware à rota
        // original. O endpoint compartilhado não pode bloquear o Filament ou a exclusão.
        if ($pedido->routeIs('*livewire.update')) {
            return $proximo($pedido);
        }

        if ($pedido->routeIs(...self::ROTAS_LIBERADAS) || $this->aceites->aceitouVigente($pessoa)) {
            return $proximo($pedido);
        }

        if ($pedido->expectsJson() || $pedido->isJson() || $pedido->hasHeader('X-Livewire')) {
            // Interrompe também o pipeline persistente: apenas retornar JSON não basta nele.
            throw new HttpResponseException(response()->json([
                'mensagem' => 'Para usar o RANÇO, leia e aceite as regras.',
            ], 403));
        }

        if ($pedido->isMethod('GET')) {
            $pedido->session()->put('url.intended', $pedido->fullUrl());
        }

        return redirect()->route('aceite.mostrar');
    }
}
