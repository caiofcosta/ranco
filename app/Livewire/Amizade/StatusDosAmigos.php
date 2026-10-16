<?php

declare(strict_types=1);

namespace App\Livewire\Amizade;

use App\Amizade\AmigosOnline;
use App\Dominio\Relogio;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;

final class StatusDosAmigos extends Component
{
    public bool $mobile = false;

    public function boot(): void
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->hasVerifiedEmail(), 403);

        app(AmigosOnline::class)->registrar(
            $usuario,
            app(Relogio::class)->agora(),
        );
    }

    public function render(): View
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return view('livewire.amizade.status-dos-amigos', [
            'status' => app(AmigosOnline::class)->de(
                $usuario,
                app(Relogio::class)->agora(),
            ),
        ]);
    }
}
