<?php

declare(strict_types=1);

namespace App\Convite;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Sinal sem dados no canal da conta convidada: a navegação mobile recarrega os convites. */
final class ConviteRecebido implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly string $pessoa) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->pessoa);
    }

    public function broadcastAs(): string
    {
        return 'convite.recebido';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
