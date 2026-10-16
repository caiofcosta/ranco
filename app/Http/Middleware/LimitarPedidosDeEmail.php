<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Acesso\LimitesDeAcesso;
use App\Acesso\TipoDeLimite;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocador;
use App\Http\Controllers\Acesso\CadastroController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limita os pedidos que enviam e-mail: cadastro (e o reenvio do link do cadastro), recuperação de
 * senha, reenvio da confirmação e link de acesso; limita também convites de amizade sem envio.
 * Vale igual para e-mails com e sem conta, para não
 * revelar quem tem conta. Pedido recusado por dados inválidos não envia e-mail e não conta.
 */
final class LimitarPedidosDeEmail
{
    private const ROTAS = ['register.store', 'cadastro.reenviar-link', 'password.email', 'verification.send', 'acesso-por-link.enviar', 'amigos.convidar'];

    public function __construct(
        private readonly LimitesDeAcesso $limites,
        private readonly Provocador $provocador,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs(...self::ROTAS)) {
            return $next($request);
        }

        $email = (string) ($request->input('email')
            ?? $request->user()->email
            ?? $request->session()->get(CadastroController::EMAIL_NA_SESSAO)
            ?? '');
        $origem = (string) $request->ip();

        $espera = max(
            $email === '' ? 0 : $this->limites->esperaNecessaria(TipoDeLimite::EmailsPorEmail, $email),
            $this->limites->esperaNecessaria(TipoDeLimite::EmailsPorOrigem, $origem),
        );

        if ($espera > 0) {
            $minutos = (int) ceil($espera / 60);
            $mensagem = 'Muitos pedidos de e-mail. Tente de novo em '.$minutos.($minutos === 1 ? ' minuto.' : ' minutos.');
            $provocacao = $this->provocador->sortear(Momento::LimiteDeEmails, 2, ['minutos' => $minutos]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $mensagem, 'provocacao' => $provocacao], 429, ['Retry-After' => (string) $espera]);
            }

            return back()
                ->withInput($request->except($request->routeIs('amigos.convidar')
                    ? ['email', 'password', 'password_confirmation']
                    : ['password', 'password_confirmation']))
                ->withErrors(['email' => $mensagem])
                ->with('provocacao', $provocacao);
        }

        $request->session()->forget('errors');
        $resposta = $next($request);

        if ($request->routeIs('amigos.convidar')) {
            // Também não persiste o endereço em old input quando a validação falha.
            $request->session()->forget('_old_input.email');
        }

        if (! $this->recusado($request, $resposta)) {
            if ($email !== '') {
                $this->limites->registrar(TipoDeLimite::EmailsPorEmail, $email);
            }
            $this->limites->registrar(TipoDeLimite::EmailsPorOrigem, $origem);
        }

        return $resposta;
    }

    /**
     * A validação recusada volta como erros na sessão (ou 422, em JSON): o Pipeline de rotas do
     * Laravel transforma a exceção em resposta antes de ela chegar aqui.
     */
    private function recusado(Request $request, Response $resposta): bool
    {
        if ($resposta->getStatusCode() === 422) {
            return true;
        }

        $erros = $request->session()->get('errors');

        return $erros instanceof ViewErrorBag && $erros->any();
    }
}
