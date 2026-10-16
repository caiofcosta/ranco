<?php

declare(strict_types=1);

namespace App\Http\Controllers\Amizade;

use Illuminate\View\View;

/** Links antigos não consultam códigos, remetentes ou destinatários. */
final class ConviteDeAmizadeController
{
    public function mostrar(): View
    {
        return view('amigos.convite');
    }

    public function aceitar(): View
    {
        return view('amigos.convite');
    }
}
