<?php

declare(strict_types=1);

namespace App\Http\Respostas;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * A recuperação de senha responde igual para e-mails com e sem conta (decisão do projeto):
 * substitui as respostas do Fortify, que diriam "não encontramos esse e-mail".
 */
final class PedidoDeRecuperacaoRecebido implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MENSAGEM = 'Link enviado. Se existir uma conta com esse e-mail, a recuperação chega em instantes.';

    public function __construct(protected string $status = '')
    {
    }

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::MENSAGEM], 200)
            : back()->with('status', self::MENSAGEM);
    }
}
