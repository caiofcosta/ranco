<?php

declare(strict_types=1);

namespace App\Mesa\Tempo;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Sinal sem dados: o navegador pede o HTML de novo, e as regras de visibilidade ficam no servidor. */
final class MesaMudou implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly string $mesaId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('mesa.'.$this->mesaId);
    }

    public function broadcastAs(): string
    {
        return 'mesa.mudou';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
