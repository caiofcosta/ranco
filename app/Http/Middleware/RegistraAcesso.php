<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Acesso\RegistrosDeAcesso;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RegistraAcesso
{
    public function __construct(private readonly RegistrosDeAcesso $registros) {}

    public function handle(Request $pedido, Closure $proximo): Response
    {
        $pessoa = $pedido->user();
        if ($pessoa instanceof User) {
            $this->registros->registrar($pessoa, $pedido);
        }

        return $proximo($pedido);
    }
}
