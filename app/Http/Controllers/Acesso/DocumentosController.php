<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acesso;

use App\Acesso\Aceites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as Pagina;

final class DocumentosController
{
    public function __invoke(Request $pedido, Aceites $aceites): Pagina
    {
        $documento = (string) $pedido->route('documento');
        $versao = (string) ($pedido->route('versao') ?? $aceites->versaoVigente());
        $pagina = 'documentos.'.$versao.'.'.$documento;
        abort_unless(View::exists($pagina), 404);

        /** @var view-string&non-falsy-string $pagina */
        return view($pagina, ['versao' => $versao]);
    }
}
