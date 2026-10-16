<?php

declare(strict_types=1);

namespace App\Livewire\Navegacao;

use App\Models\DismissaoDeModal;
use App\Models\ModalDeNotificacao;
use App\Models\User;
use App\Navegacao\ContextoMobile;
use App\Navegacao\DestaqueDaHome;
use App\Tom\FalaDoTom;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class AreaMobile extends Component
{
    #[Locked]
    public string $area = 'inicio';

    #[Locked]
    public ?array $fala = null;

    public function mount(string $area = 'inicio'): void
    {
        $this->area = $area;
        if ($area === 'inicio') {
            $usuario = auth()->user();
            abort_unless($usuario instanceof User && $usuario->hasVerifiedEmail(), 403);
            $fala = app(FalaDoTom::class)->para($usuario->identificadorNoDominio());
            $this->fala = $fala ? ['texto' => $fala->texto, 'pose' => $fala->pose, 'expressao' => $fala->expressao] : null;
        }
    }

    private function contarAvisosNaoLidos(User $usuario): int
    {
        $idsAtivos = ModalDeNotificacao::ativas()->pluck('id');

        if ($idsAtivos->isEmpty()) {
            return 0;
        }

        $lidas = DismissaoDeModal::where('user_id', $usuario->id)
            ->whereIn('modal_de_notificacao_id', $idsAtivos)
            ->whereNotNull('visualizou_em')
            ->count();

        return $idsAtivos->count() - $lidas;
    }

    #[On('convites-mudaram')]
    public function atualizarConvites(): void {}

    public function render(): View
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User && $usuario->hasVerifiedEmail(), 403);

        return view('livewire.navegacao.area-mobile', [
            'contexto'       => app(ContextoMobile::class)->de($usuario),
            'destaque'       => $this->area === 'inicio' ? app(DestaqueDaHome::class)->principal() : null,
            'avisosNaoLidos' => $this->area === 'mais' ? $this->contarAvisosNaoLidos($usuario) : 0,
        ]);
    }
}
