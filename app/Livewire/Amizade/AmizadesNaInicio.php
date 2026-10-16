<?php

declare(strict_types=1);

namespace App\Livewire\Amizade;

use App\Amizade\AmigosOnline;
use App\Amizade\ConvitesDeAmizade;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocador;
use App\Dominio\Relogio;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;

/** Segunda coluna do Início, atualizada na mesma cadência do contador do menu (45 s). */
final class AmizadesNaInicio extends Component
{
    /** Sorteada uma vez por abertura da página, para não trocar a cada atualização. */
    public ?string $provocacao = null;

    public function mount(Provocador $provocador): void
    {
        $this->provocacao = $provocador->sortear(Momento::AmizadeVazia);
    }

    public function render(AmigosOnline $amigosOnline, ConvitesDeAmizade $convites, Plataforma $plataforma, Relogio $relogio): View
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $amigos = $amigosOnline->de($usuario, $relogio->agora());
        $pedidos = $convites->recebidos($usuario);
        $pessoas = User::query()->whereIn('id', $pedidos->pluck('de'))->get(['id', 'name', 'google_avatar_url'])->keyBy('id');

        return view('livewire.amizade.amizades-na-inicio', [
            'amigos' => $amigos,
            'pedidos' => $pedidos->map(static fn ($pedido): array => [
                'pedido' => $pedido,
                'nome' => $pessoas->get($pedido->de)->name ?? 'Alguém',
                'foto' => $pessoas->get($pedido->de)?->google_avatar_url,
            ])->all(),
            'invisivel' => $plataforma->estaInvisivel($usuario->identificadorNoDominio()),
        ]);
    }
}
