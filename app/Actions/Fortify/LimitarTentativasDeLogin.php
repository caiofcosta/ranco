<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Acesso\LimitesDeAcesso;
use App\Acesso\TipoDeLimite;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocador;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Etapa da sequência de login do Fortify: aplica o limite com pausa e devolve, junto do erro, uma
 * provocação que escala a cada erro. A contagem é pelo e-mail digitado, exista conta ou não,
 * para a resposta não revelar quem tem conta.
 */
final class LimitarTentativasDeLogin
{
    public function __construct(
        private readonly LimitesDeAcesso $limites,
        private readonly Provocador $provocador,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $email = (string) $request->input(Fortify::username());
        $origem = (string) $request->ip();

        $this->exigirLiberado($request, $email, $origem);

        $request->session()->forget('errors');
        $resposta = $next($request);

        if (! $this->falhou($request, $resposta)) {
            $this->limites->zerar(TipoDeLimite::LoginPorEmail, $email);

            return $resposta;
        }

        $this->limites->registrar(TipoDeLimite::LoginPorOrigem, $origem);
        $erros = $this->limites->registrar(TipoDeLimite::LoginPorEmail, $email);
        $this->exigirLiberado($request, $email, $origem);

        throw $this->recusa(
            $request,
            'E-mail ou senha incorretos.',
            $this->provocador->sortear(Momento::ErroDeLogin, min($erros, 3), ['tentativa' => $erros]),
        );
    }

    /**
     * O Pipeline de rotas do Laravel transforma a exceção de credenciais erradas em resposta antes
     * de ela chegar aqui: o erro fica na sessão (ou volta como 422, em JSON).
     */
    private function falhou(Request $request, mixed $resposta): bool
    {
        if (Auth::guard(config('fortify.guard'))->check()) {
            return false;
        }

        if ($resposta instanceof Response && $resposta->getStatusCode() === 422) {
            return true;
        }

        $erros = $request->session()->get('errors');

        return $erros instanceof ViewErrorBag && $erros->has(Fortify::username());
    }

    private function exigirLiberado(Request $request, string $email, string $origem): void
    {
        $espera = max(
            $this->limites->esperaNecessaria(TipoDeLimite::LoginPorEmail, $email),
            $this->limites->esperaNecessaria(TipoDeLimite::LoginPorOrigem, $origem),
        );
        if ($espera === 0) {
            return;
        }

        $minutos = (int) ceil($espera / 60);
        $request->session()->flash('pausa_em_minutos', $minutos);

        throw $this->recusa(
            $request,
            'Muitas tentativas. Tente de novo em '.$minutos.($minutos === 1 ? ' minuto.' : ' minutos.'),
            $this->provocador->sortear(Momento::PausaDeLogin, 2, ['minutos' => $minutos]),
        );
    }

    private function recusa(Request $request, string $mensagem, ?string $provocacao): ValidationException
    {
        if ($provocacao !== null) {
            $request->session()->flash('provocacao', $provocacao);
        }

        return ValidationException::withMessages([Fortify::username() => $mensagem]);
    }
}
