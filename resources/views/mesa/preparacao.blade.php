{{-- Cabeçalho mobile da mesa: nome à esquerda, administrador à direita, numa linha. --}}
<header class="flex items-center justify-between gap-3 md:hidden">
    <h1 class="min-w-0 truncate text-lg font-black leading-tight tracking-[-0.03em] sm:text-2xl" title="{{ $tela->nome }}" data-test="nome-da-mesa">{{ $tela->nome }}</h1>
    <p class="max-w-[45%] shrink-0 text-right text-[.7rem] leading-tight text-muted sm:text-xs" data-test="contexto-da-mesa">
        Administrador
        <span class="block truncate text-[.8rem] font-bold text-coal sm:text-sm">{{ $nomeAdmin }}</span>
    </p>
    <button type="button" x-on:click="$refs.opcoes.showModal()" class="hidden min-h-11 shrink-0 rounded-xl border border-coal/20 bg-white px-5 font-bold sm:block md:hidden">Opções da mesa</button>
</header>

@if ($partida->estado !== 'andamento')
    <section class="mt-5 hidden flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-coal/10 bg-white px-4 py-2 sm:flex md:hidden" aria-labelledby="titulo-preparacao" data-test="preparacao-da-mesa">
        <div class="mr-auto">
            @php
                $todosPreenchidos = count($pessoas) === $quantidadeLugares;
                $temJogo = (bool) $configuracaoJogo;
            @endphp
            @if ($temJogo && $partida->podeIniciar)
                <h2 id="titulo-preparacao" class="text-sm font-black">Tudo pronto.</h2>
                <p class="text-xs text-muted">Quando quiser, pode começar.</p>
            @elseif ($todosPreenchidos && !$temJogo)
                <h2 id="titulo-preparacao" class="text-sm font-black">Todo mundo chegou.</h2>
                <p class="text-xs text-muted">Agora só falta escolher o jogo.</p>
            @else
                <h2 id="titulo-preparacao" class="text-sm font-black">Tá quase.</h2>
                <p class="text-xs text-muted">Chame a galera e escolha o jogo. A ordem não importa.</p>
            @endif
        </div>
        <ol class="flex flex-wrap items-center gap-2 text-xs font-bold">
            <li class="rounded-full border border-green-200 bg-green-50 px-3 py-2 text-green-900">✓ Mesa criada</li>
            <li>
                <button type="button" @if ($tela->ehAdmin) wire:click="abrirLugares" aria-haspopup="dialog" @else disabled @endif
                    @class([
                        'min-h-9 cursor-pointer rounded-full border px-3 py-2 transition-colors hover:border-coal/40 hover:bg-coal/10 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:cursor-default',
                        'border-green-200 bg-green-50 text-green-900' => $configuracaoJogo && $partida->podeIniciar,
                        'border-coral/40 bg-coral/10' => $configuracaoJogo && ! $partida->podeIniciar,
                        'border-coal/10 bg-cream' => ! $configuracaoJogo,
                    ])>
                    {{ $configuracaoJogo && $partida->podeIniciar ? '✓' : '○' }}
                    {{ count($pessoas) }}/{{ $quantidadeLugares }} jogadores
                    <span class="sr-only">{{ $configuracaoJogo && $partida->podeIniciar ? '✓ Jogadores prontos' : 'Configure os lugares e prepare os jogadores' }}</span>
                </button>
            </li>
            <li>
                <button type="button" @if ($tela->ehAdmin) wire:click="abrirEscolhaDoJogo" @else disabled @endif
                    @class([
                        'min-h-9 cursor-pointer rounded-full border px-3 py-2 transition-colors hover:border-coal/40 hover:bg-coal/10 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:cursor-default',
                        'border-green-200 bg-green-50 text-green-900' => $configuracaoJogo,
                        'border-coral/40 bg-coral/10' => ! $configuracaoJogo,
                    ])>
                    {{ $configuracaoJogo ? '✓ '.\App\Dominio\Jogos\CatalogoDeJogos::nome($configuracaoJogo->jogo ?? 'labirinto') : '○ Escolher jogo' }}
                    <span class="sr-only">{{ $configuracaoJogo ? 'Jogo escolhido · Revisar configuração' : 'Nenhum jogo escolhido' }}</span>
                </button>
            </li>
        </ol>
    </section>
@endif
