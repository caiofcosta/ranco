<?php

declare(strict_types=1);

namespace App\Convite;

use Illuminate\Support\Facades\URL;

/** URL assinada do e-mail: o id do convite já é aleatório; a assinatura impede forjar o endereço. */
final class LinkDoConvite
{
    public static function assinado(string $conviteId): string
    {
        return URL::signedRoute('convites.entrar', ['convite' => $conviteId]);
    }

    public static function aceitar(string $conviteId): string
    {
        return URL::signedRoute('convites.aceitar', ['convite' => $conviteId]);
    }
}
