<x-layouts::app title="Amigos">
    {{-- Chamada: a mesma moça do Início, num painel coral, com o convite logo abaixo. --}}
    <section class="overflow-hidden rounded-[28px] bg-sand" data-test="amigos-chamada">
        <div class="grid items-center gap-6 p-7 sm:p-9 lg:grid-cols-[1fr_auto] lg:gap-10 lg:p-10">
            <div class="min-w-0">
                <p class="text-[.63rem] font-black uppercase tracking-[.16em] text-muted">Amizades</p>
                <h1 class="mt-2 text-[clamp(2rem,4vw,3rem)] font-black leading-[0.95] tracking-[-0.05em]">Jogue junto.</h1>
                <p class="mt-4 max-w-md text-[15px] leading-relaxed text-coal/80">Adicione a galera para convidar para suas mesas e entrar nas deles.</p>
            </div>
            <div class="hidden justify-self-end lg:block">
                <div class="grid size-[230px] place-items-center overflow-hidden rounded-[22px] bg-coral">
                    <img src="{{ asset('images/home/adicionar-amigo.png') }}" alt="" class="size-[210px] object-contain">
                </div>
            </div>
        </div>
    </section>

    <div class="mt-4 lg:hidden"><livewire:amizade.status-dos-amigos :mobile="true" /></div>

    @if (session('status'))
        <x-aviso class="mt-6 max-w-lg">{{ session('status') }}</x-aviso>
    @endif
    @error('amizade')
        <p class="mt-6 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror

    <div class="mt-6 grid gap-5 lg:mt-8 lg:grid-cols-2">
        {{-- Convite por e-mail --}}
        <section class="rounded-[22px] border border-coal/10 bg-white p-6 sm:p-7">
            <h2 class="text-xl font-black tracking-[-0.03em]">Convide alguém para jogar</h2>
            <p class="mt-1 text-sm text-muted">Digite o e-mail que a pessoa usa no RANÇO.</p>

            <form method="POST" action="{{ route('amigos.convidar') }}" class="mt-5" data-test="convidar-amizade">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <label for="email" class="sr-only">E-mail da pessoa</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="off"
                        placeholder="amizade@email.com"
                        @class([
                            'h-14 w-full rounded-xl border bg-cream/60 px-4 text-[15px] text-coal outline-none transition placeholder:text-muted focus:border-coal focus:ring-4 focus:ring-coral/15',
                            'border-danger' => $errors->has('email'),
                            'border-coal/10' => ! $errors->has('email'),
                        ])
                    >
                    <x-botao class="sm:w-auto sm:shrink-0 sm:px-7">Enviar convite</x-botao>
                </div>
                <x-retorno :erros="$errors->get('email')" />
                <p class="mt-3 text-sm text-muted">Se houver uma conta com esse e-mail, a pessoa vai ver seu convite.</p>
            </form>
        </section>

        {{-- Como funciona --}}
        <section class="rounded-[22px] border border-coal/10 bg-white p-6 sm:p-7">
            <p class="text-[.63rem] font-black uppercase tracking-[.16em] text-muted">Como funciona</p>
            <ol class="mt-4 grid gap-5">
                <li class="flex gap-4">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-coal text-sm font-black text-cream" aria-hidden="true">1</span>
                    <div class="min-w-0">
                        <p class="font-black text-coal">Convide</p>
                        <p class="mt-0.5 text-sm text-muted">Digite o e-mail da pessoa.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-coal text-sm font-black text-cream" aria-hidden="true">2</span>
                    <div class="min-w-0">
                        <p class="font-black text-coal">A pessoa aceita</p>
                        <p class="mt-0.5 text-sm text-muted">O convite aparece para ela.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-coal text-sm font-black text-cream" aria-hidden="true">3</span>
                    <div class="min-w-0">
                        <p class="font-black text-coal">Joguem juntos</p>
                        <p class="mt-0.5 text-sm text-muted">Entrem nas mesas um do outro.</p>
                    </div>
                </li>
            </ol>
        </section>
    </div>

    @if ($recebidos->isNotEmpty())
        <section class="mt-12 max-w-2xl">
            <h2 class="text-lg font-black tracking-[-0.03em]">Convites recebidos</h2>
            <ul class="mt-4 grid gap-2">
                @foreach ($recebidos as $convite)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3">
                        <span class="font-bold">{{ $nomes[$convite->de] ?? 'Alguém' }}</span>
                        <span class="flex items-center gap-4">
                            <form method="POST" action="{{ route('amigos.convites.recusar', $convite) }}">
                                @csrf
                                <button type="submit" class="rounded text-sm font-semibold text-muted transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Recusar</button>
                            </form>
                            <form method="POST" action="{{ route('amigos.convites.aceitar', $convite) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-10 items-center rounded-full bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Aceitar</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-12 max-w-2xl">
        <h2 class="text-lg font-black tracking-[-0.03em]">Suas amizades</h2>
        @if ($amigos->isEmpty())
            <p class="mt-4 text-sm text-muted">Nenhuma amizade ainda.</p>
        @else
            <ul class="mt-4 grid gap-2">
                @foreach ($amigos as $amigo)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3">
                        <x-link :href="route('perfil.amigo', (string) $amigo->id)">{{ $amigo->name }}</x-link>
                        <span class="flex items-center gap-4">
                            <form method="POST" action="{{ route('amigos.desfazer', (string) $amigo->id) }}" onsubmit="return confirm('Desfazer a amizade cancela os convites de mesa pendentes entre vocês.')">
                                @csrf
                                <button type="submit" class="rounded text-sm font-semibold text-muted transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Desfazer amizade</button>
                            </form>
                            <form method="POST" action="{{ route('amigos.bloquear', (string) $amigo->id) }}" onsubmit="return confirm('Bloquear desfaz a amizade e impede novos convites dessa pessoa.')">
                                @csrf
                                <button type="submit" class="rounded text-sm font-semibold text-danger transition hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Bloquear</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

</x-layouts::app>
