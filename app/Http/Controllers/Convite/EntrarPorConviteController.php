<?php

declare(strict_types=1);

namespace App\Http\Controllers\Convite;

use App\Convite\LinkDoConvite;
use App\Convite\MensagemDoConvite;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\NaoEncontrado;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Dominio\Social\Convite;
use App\Dominio\Social\SituacaoConvite;
use App\Http\Controllers\Controller;
use App\Mesa\NomeDaMesa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Link do e-mail de convite de mesa. Quem não está logado passa pelo login ou pelo cadastro e
 * volta para cá. Aceitar é sempre um POST, para o cliente de e-mail não entrar na mesa sozinho.
 */
final class EntrarPorConviteController extends Controller
{
    public function __construct(private readonly Plataforma $plataforma)
    {
    }

    public function mostrar(string $convite): View|RedirectResponse
    {
        $convite = $this->convite($convite);
        $eu = $this->usuario();
        $identificador = $eu->identificadorNoDominio();

        if ($convite->para !== $identificador) {
            return view('convite.entrar', [
                'estado' => $convite->de === $identificador ? 'proprio' : 'alheio',
                'nomeConvidante' => null,
                'nomeMesa' => null,
                'urlAceitar' => null,
            ]);
        }

        $mesa = $this->plataforma->mesa($convite->mesaId);

        if ($mesa->membroAtivo($identificador)) {
            return redirect()->route('mesas.mostrar', $mesa->id);
        }

        if ($convite->situacao !== SituacaoConvite::Pendente || $mesa->situacao() !== SituacaoMesa::Aberta) {
            return view('convite.entrar', [
                'estado' => $mesa->situacao() !== SituacaoMesa::Aberta ? 'encerrado' : 'usado',
                'nomeConvidante' => null,
                'nomeMesa' => null,
                'urlAceitar' => null,
            ]);
        }

        return view('convite.entrar', [
            'estado' => 'valido',
            'nomeConvidante' => $this->nomeDe($convite->de),
            'nomeMesa' => NomeDaMesa::de($mesa),
            'urlAceitar' => LinkDoConvite::aceitar($convite->id),
        ]);
    }

    public function aceitar(string $convite): RedirectResponse
    {
        $convite = $this->convite($convite);
        $eu = $this->usuario();

        try {
            $this->plataforma->aceitarConvite($convite->id, $eu->identificadorNoDominio());
        } catch (RegraViolada $erro) {
            return redirect()->to(LinkDoConvite::assinado($convite->id))
                ->withErrors(['convite' => MensagemDoConvite::de($erro)]);
        }

        return redirect()->route('mesas.mostrar', $convite->mesaId);
    }

    private function convite(string $id): Convite
    {
        try {
            return $this->plataforma->convite($id);
        } catch (NaoEncontrado) {
            abort(404);
        }
    }

    private function usuario(): User
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario;
    }

    private function nomeDe(string $identificador): string
    {
        return User::query()->find($identificador)?->name ?? 'Alguém';
    }
}
