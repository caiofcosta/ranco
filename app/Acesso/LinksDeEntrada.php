<?php

declare(strict_types=1);

namespace App\Acesso;

use App\Models\User;
use App\Notifications\ConfirmacaoDeEmail;
use App\Notifications\LinkDeAcesso;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Links enviados por e-mail que entram na conta: o de acesso (30 minutos) e o de confirmação do
 * cadastro (48 horas, em auth.verification.expire). Os dois são assinados e funcionam uma vez só.
 */
final class LinksDeEntrada
{
    public const MINUTOS_DO_ACESSO = 30;

    /** `pediuCadastro`: a pessoa tentou criar uma conta com um e-mail que já tem conta. */
    public function enviarAcesso(User $usuario, bool $pediuCadastro = false): void
    {
        $usuario->notify(new LinkDeAcesso($this->criar('acesso-por-link.entrar', $usuario, self::MINUTOS_DO_ACESSO), $pediuCadastro));
    }

    public function enviarConfirmacao(User $usuario): void
    {
        $usuario->notify(new ConfirmacaoDeEmail($this->criar('confirmacao-de-email', $usuario, $this->minutosDaConfirmacao())));
    }

    /** Registra o uso do código do link. Devolve false se ele já tinha sido usado. */
    public function usar(string $codigo): bool
    {
        return Cache::add(
            'link-de-entrada:'.hash('sha256', $codigo),
            true,
            now()->addMinutes(max(self::MINUTOS_DO_ACESSO, $this->minutosDaConfirmacao())),
        );
    }

    private function minutosDaConfirmacao(): int
    {
        return (int) config('auth.verification.expire', 2880);
    }

    private function criar(string $rota, User $usuario, int $minutos): string
    {
        return URL::temporarySignedRoute($rota, now()->addMinutes($minutos), [
            'usuario' => $usuario->id,
            'codigo' => Str::random(40),
        ]);
    }
}
