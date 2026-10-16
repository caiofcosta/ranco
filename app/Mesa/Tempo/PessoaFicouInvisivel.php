<?php

declare(strict_types=1);

namespace App\Mesa\Tempo;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Sinal sem dados no canal da própria conta: as abas abertas saem das presenças e recarregam. */
final class PessoaFicouInvisivel implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly string $pessoa) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->pessoa);
    }

    public function broadcastAs(): string
    {
        return 'pessoa.ficou-invisivel';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
