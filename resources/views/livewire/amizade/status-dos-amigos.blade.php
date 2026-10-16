<div
    wire:poll.45s
    class="shrink-0 px-[11px] pb-2 lg:pl-[52px] {{ $mobile ? 'mobile-status-amigos' : '' }}"
    data-test="status-dos-amigos"
>
    <span wire:offline class="text-xs text-cream/60">
        Sem conexão
    </span>

    <div wire:offline.remove>
        <flux:modal.trigger name="{{ $mobile ? 'status-dos-amigos-mobile' : 'status-dos-amigos' }}">
            <button
                type="button"
                class="relative inline-flex min-h-8 items-center whitespace-nowrap rounded text-xs text-cream/80 transition hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral"
                aria-haspopup="dialog"
                data-test="abrir-status-dos-amigos"
            >
                {{ $status['online'] }} online · {{ $status['offline'] }} offline<span class="sr-only">, ver lista</span>
            </button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="{{ $mobile ? 'status-dos-amigos-mobile' : 'status-dos-amigos' }}" class="w-full max-w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Quem está por aí?</flux:heading>
                <flux:subheading>
                    {{ $status['online'] }} online · {{ $status['offline'] }} offline
                </flux:subheading>
            </div>

            <p wire:offline role="status" class="text-sm text-muted">
                Sem conexão. Estes dados podem estar desatualizados.
            </p>

            @if ($status['pessoas'] === [])
                <p class="text-sm text-muted">Nenhuma amizade ainda.</p>
            @else
                <ul class="max-h-[50dvh] space-y-3 overflow-y-auto">
                    @foreach ($status['pessoas'] as $pessoa)
                        <li class="flex items-center justify-between gap-4 border-b border-coal/10 pb-3">
                            <span class="min-w-0 break-words font-bold text-coal">
                                {{ $pessoa['nome'] }}
                            </span>
                            <span class="shrink-0 text-xs text-muted">
                                @if ($pessoa['online'])
                                    <span
                                        class="mr-1 inline-block size-2 rounded-full bg-coral"
                                        aria-hidden="true"
                                    ></span>
                                @endif
                                {{ $pessoa['visto'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button>Fechar</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
