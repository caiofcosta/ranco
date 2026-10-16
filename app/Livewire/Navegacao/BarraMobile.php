<?php

declare(strict_types=1);

namespace App\Livewire\Navegacao;

use App\Models\User;
use App\Navegacao\ContextoMobile;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class BarraMobile extends Component
{
    #[Locked]
    public string $ativa = 'mais';

    /** As mesmas cinco áreas num menu do topo, para telas que dispensam a barra inferior. */
    #[Locked]
    public bool $menu = false;

    public function mount(): void
    {
        $this->ativa = match (true) {
            request()->routeIs('dashboard', 'mobile.inicio') => 'inicio',
            request()->routeIs('mobile.pedidos') => 'pedidos',
            request()->routeIs('mobile.jogar-juntos', 'mesas.*') => 'jogar-juntos',
            request()->routeIs('jogar.index') => 'jogar-solo',
            default => 'mais',
        };
    }

    #[On('convites-mudaram')]
    public function atualizarConvites(): void {}

    public function render(): View
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 401);

        return view('livewire.navegacao.barra-mobile', [
            'contexto' => $usuario->hasVerifiedEmail() ? app(ContextoMobile::class)->de($usuario) : ['total' => 0, 'participacoes' => []],
        ]);
    }
}
