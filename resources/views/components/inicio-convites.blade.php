@props(['convites', 'principal' => true, 'invisivel' => false, 'semVaga' => false])

<section class="mb-10" data-test="convites-na-home" aria-labelledby="convites-titulo">
    <h2 id="convites-titulo" class="flex items-center gap-3 text-2xl font-black tracking-[-0.04em]">
        <flux:icon.envelope class="size-7" />
        Convites
        <span class="grid h-7 min-w-7 place-items-center rounded-full bg-coral px-2 text-sm font-black text-coal">{{ count($convites) }}</span>
    </h2>

    @error('convite')
        <p class="mt-4 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror

    <ul class="mt-5 grid gap-4 @4xl:grid-cols-2">
        @foreach ($convites as $convite)
            {{-- data-presenca-convite: com a Início aberta, o admin vê este convidado online na mesa. --}}
            <li class="flex flex-wrap items-center gap-4 rounded-[22px] border border-coal/10 bg-white p-5" data-test="convite-na-home" @unless ($invisivel) data-presenca-convite="{{ $convite->mesaId }}" @endunless>
                <x-avatar :nome="$convite->nomeConvidante" :foto="$convite->foto" class="size-14 rounded-full bg-sand text-base font-black text-coal" />
                <div class="min-w-0 flex-1 basis-40">
                    <p class="text-sm [overflow-wrap:anywhere]"><strong class="font-black">{{ $convite->nomeConvidante }}</strong> convidou você</p>
                    <h3 class="mt-1 text-lg font-black tracking-[-0.03em] [overflow-wrap:anywhere]">{{ $convite->nomeMesa }}</h3>
                    <p class="mt-1 flex items-center gap-1.5 text-sm text-muted">
                        <flux:icon.users variant="micro" />
                        {{ $convite->quantasPessoas }} {{ $convite->quantasPessoas === 1 ? 'pessoa' : 'pessoas' }}
                    </p>
                </div>
                <div class="grid w-full gap-2 sm:w-40">
                    @if ($invisivel)
                        <p class="text-sm text-muted">Fique visível para aceitar.</p>
                    @elseif ($semVaga)
                        <p class="text-sm text-muted" data-test="convite-sem-vaga">Você está no máximo de mesas. Saia de uma para aceitar.</p>
                    @else
                        <form method="POST" action="{{ route('inicio.convites.aceitar', $convite->id) }}">
                            @csrf
                            <button
                                type="submit"
                                @class([
                                    'inline-flex h-11 w-full items-center justify-center rounded-xl text-sm font-black transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal',
                                    'bg-coral text-coal hover:shadow-lg' => $principal,
                                    'bg-coal text-cream' => ! $principal,
                                ])
                                data-test="aceitar-convite"
                            >Aceitar</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('inicio.convites.recusar', $convite->id) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-coal/5 text-sm font-black text-coal transition hover:bg-coal/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="recusar-convite">Recusar</button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>
</section>

@vite('resources/js/mesa/tempo-real.js')
