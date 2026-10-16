<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dominio\Plataforma\Plataforma;
use App\Inicio\SuasMesasDaHome;
use App\Navegacao\ContextoMobile;
use App\Navegacao\DestaqueDaHome;
use App\Navegacao\ResumoDaHome;
use App\Models\User;
use App\Servicos\RepositorioDeModaisDeNotificacao;
use App\Tom\FalaDoTom;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Início autenticado: o Tom com a saudação; na primeira coluna, convites e mesas; na segunda, as amizades (componente próprio). */
final class InicioController extends Controller
{
    public function __invoke(FalaDoTom $falas, Plataforma $plataforma, RepositorioDeModaisDeNotificacao $repositorioModais, string $areaMobile = 'inicio'): View
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $identificador = $usuario->identificadorNoDominio();
        $suasMesas = SuasMesasDaHome::para($plataforma, $identificador);
        $mesas = $suasMesas['mesas'];
        $convites = $suasMesas['convites'];
        $resultados = $suasMesas['resultados'];
        $vagasDeMesa = $suasMesas['vagasDeMesa'];

        return view('dashboard', [
            'areaMobile' => $areaMobile,
            'nome' => Str::before(trim($usuario->name), ' '),
            'fala' => $falas->para($identificador),
            'mesas' => $mesas,
            'vagasDeMesa' => $vagasDeMesa,
            'resultados' => $resultados,
            'criarMesaEmDestaque' => $mesas === [] && $convites === [] && $resultados === [],
            // Com mesas em andamento, a ação principal fica nelas; sem vaga, aceitar fica para depois.
            'aceitarEmDestaque' => $mesas === [] && $resultados === [] && $vagasDeMesa > 0,
            'convites' => $convites,
            'resumo' => ResumoDaHome::itens(app(ContextoMobile::class)->de($usuario)),
            'destaque' => app(DestaqueDaHome::class)->principal(),
            'invisivel' => $suasMesas['invisivel'],
            'modalDeNotificacao' => $repositorioModais->obterModalParaUsuario($usuario),
        ]);
    }
}
