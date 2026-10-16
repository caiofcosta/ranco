<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inicio;

use App\Dominio\Plataforma\Plataforma;
use App\Inicio\SuasMesasDaHome;
use App\Models\User;
use Illuminate\View\View;

/** Só o bloco "Suas mesas": a Início troca esse trecho quando chega um aviso, sem recarregar a página. */
final class SuasMesasController
{
    public function __invoke(Plataforma $plataforma): View
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return view('inicio.suas-mesas', SuasMesasDaHome::para($plataforma, $usuario->identificadorNoDominio()));
    }
}
