<?php

declare(strict_types=1);

namespace App\Acesso;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Conta tentativas no cache do Laravel. Ao atingir o limite, abre uma pausa própria, contada a
 * partir daquele momento, e zera a contagem: depois da pausa, a pessoa recomeça do zero.
 * As chaves guardam só o hash do e-mail ou do IP.
 */
final class LimitesDeAcesso
{
    /** Segundos que faltam para poder tentar de novo; 0 se já pode. */
    public function esperaNecessaria(TipoDeLimite $tipo, string $chave): int
    {
        $pausa = $this->chave('pausa', $tipo, $chave);

        return RateLimiter::tooManyAttempts($pausa, 1) ? max(1, RateLimiter::availableIn($pausa)) : 0;
    }

    /** Registra a tentativa e devolve quantas houve na janela, contando esta. */
    public function registrar(TipoDeLimite $tipo, string $chave): int
    {
        $contagem = $this->chave('contagem', $tipo, $chave);
        RateLimiter::hit($contagem, $tipo->janelaEmSegundos());
        $tentativas = RateLimiter::attempts($contagem);

        if ($tentativas >= $tipo->limite()) {
            RateLimiter::hit($this->chave('pausa', $tipo, $chave), $tipo->pausaEmSegundos());
            RateLimiter::clear($contagem);
        }

        return $tentativas;
    }

    public function zerar(TipoDeLimite $tipo, string $chave): void
    {
        RateLimiter::clear($this->chave('contagem', $tipo, $chave));
    }

    private function chave(string $parte, TipoDeLimite $tipo, string $chave): string
    {
        return "acesso:{$parte}:{$tipo->value}:".hash('sha256', mb_strtolower(trim($chave)));
    }
}
