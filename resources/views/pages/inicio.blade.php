{{--
    Home pública: a marca embaixo, e o login real no painel grafite do topo.
    Quem já entrou vê o caminho de volta, no lugar do formulário.
--}}
@php
    $entrou = auth()->check();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head', ['title' => 'Início'])
    </head>
    <body class="bg-cream text-coal antialiased">
        <header class="relative overflow-hidden bg-coal text-cream">
            <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 size-64 rounded-full border-[36px] border-coral sm:-right-20 sm:-top-28 sm:size-72"></div>

            <div class="relative z-10 mx-auto w-[min(calc(100%-1.875rem),1240px)] sm:w-[min(calc(100%-3rem),1240px)]">
                <nav class="flex h-20 items-center justify-between gap-5 sm:h-24">
                    <a href="{{ route('home') }}" class="rounded text-[1.58rem] font-black tracking-[-0.06em] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral">
                        RANÇO<span class="text-coral">.</span>
                    </a>

                    <div class="flex items-center gap-7 text-[.82rem] font-extrabold text-cream/70">
                        <a href="#entenda" class="hidden rounded hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral md:inline">O que é</a>
                        <a href="#jogos" class="hidden rounded hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral md:inline">Jogos</a>
                        <a href="#exemplo" class="hidden rounded hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral md:inline">Exemplo</a>
                        @if ($entrou)
                            <a href="{{ route('dashboard') }}" class="inline-flex min-h-[52px] items-center justify-center rounded-[14px] border border-cream/20 px-6 text-[.8rem] font-black uppercase tracking-[.06em] text-cream transition hover:border-cream/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral">Continuar</a>
                        @else
                            <a href="#entrar" class="inline-flex min-h-[52px] items-center justify-center rounded-[14px] border border-cream/20 px-6 text-[.8rem] font-black uppercase tracking-[.06em] text-cream transition hover:border-cream/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral">Entrar</a>
                        @endif
                    </div>
                </nav>

                <div class="grid items-center gap-12 py-10 pb-20 lg:min-h-[664px] lg:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)] lg:gap-14 lg:py-12 lg:pb-24">
                    <div class="min-w-0 max-w-[690px]">
                        <p class="mb-[18px] text-[.72rem] font-black uppercase leading-none tracking-[.22em] text-cream/50">Competições absolutamente necessárias*</p>
                        <h1 class="max-w-full text-[clamp(2.15rem,4.6vw,4.6rem)] font-black leading-[.88] tracking-[-0.07em]">
                            JOGAR É FÁCIL.
                            <span class="block text-coral">PERDER SEM RANÇO É QUE É DIFÍCIL.</span>
                        </h1>
                        <p class="mt-7 max-w-[650px] text-[1.08rem] leading-[1.72] text-cream/70">
                            Crie uma mesa com amigos, casal, família ou colegas. Joguem desafios rápidos,
                            comparem resultados e transformem cada partida em parte de uma rivalidade que continua.
                        </p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            <a href="#cta" class="inline-flex min-h-[52px] items-center justify-center rounded-[14px] bg-coral px-6 text-[.8rem] font-black uppercase tracking-[.06em] text-coal transition hover:-translate-y-0.5 hover:shadow-[0_14px_30px_rgba(255,104,77,.22)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cream">Começar a jogar</a>
                            <a href="#entenda" class="inline-flex min-h-[52px] items-center justify-center rounded-[14px] border border-cream/20 px-6 text-[.8rem] font-black uppercase tracking-[.06em] text-cream transition hover:border-cream/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral">Entender o RANÇO</a>
                        </div>
                        <p class="mt-5 text-[.78rem] text-cream/40">* O prêmio é simples: ter assunto até a próxima partida.</p>
                    </div>

                    <div class="flex min-w-0 justify-start lg:justify-end">
                        <div class="relative w-full max-w-[26rem]">
                            <div aria-hidden="true" class="absolute -left-4 -top-4 size-24 rounded-[28px] bg-coral"></div>
                            <div id="entrar" class="relative w-full rounded-[30px] bg-white p-7 text-coal shadow-[0_34px_90px_rgba(0,0,0,.34)] sm:p-8">
                                @if ($entrou)
                                    <p class="text-[.66rem] font-black uppercase tracking-[.16em] text-muted">A mesa não esqueceu você</p>
                                    <h2 class="mt-2 text-[1.8rem] font-black leading-none tracking-[-0.045em]">Você já entrou.</h2>
                                    <p class="mb-6 mt-1 text-[.88rem] text-muted">A disputa continua de onde parou.</p>
                                    <a href="{{ route('dashboard') }}" class="inline-flex h-14 w-full items-center justify-center rounded-xl bg-coral text-sm font-black uppercase tracking-[.08em] text-coal transition hover:-translate-y-px hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Continuar</a>
                                @else
                                    <p class="text-[.66rem] font-black uppercase tracking-[.16em] text-muted">Já começou a disputa?</p>
                                    <h2 class="mt-2 text-[1.8rem] font-black leading-none tracking-[-0.045em]">Entre na sua conta.</h2>
                                    <p class="mt-1 text-[.88rem] text-muted">Sua mesa pode estar só esperando você aparecer.</p>

                                    @if (session('status'))
                                        <x-aviso class="mt-5">{{ session('status') }}</x-aviso>
                                    @endif

                                    @if (\App\Http\Controllers\Acesso\ContaGoogleController::configurado())
                                        <div class="mt-5">
                                            <x-botao-google />
                                        </div>
                                        <x-separador texto="ou use aquilo que você decorou" />
                                    @endif

                                    <form method="POST" action="{{ route('login.store') }}" class="mt-5 space-y-4">
                                        @csrf
                                        <input type="hidden" name="remember" value="1">

                                        <x-campo
                                            nome="email"
                                            rotulo="E-mail"
                                            tipo="email"
                                            :value="old('email')"
                                            required
                                            autofocus
                                            autocomplete="email"
                                            placeholder="voce@email.com"
                                            :mostrar-erro="false"
                                        />

                                        <x-campo
                                            nome="password"
                                            rotulo="Senha"
                                            tipo="password"
                                            required
                                            autocomplete="current-password"
                                            placeholder="Sua senha"
                                            revelavel
                                            :mostrar-erro="false"
                                        >
                                            <x-slot:acao>
                                                <x-link discreto :href="route('password.request')" class="text-xs" aria-label="Esqueci minha senha">Esqueci</x-link>
                                            </x-slot:acao>
                                        </x-campo>

                                        <x-retorno :erros="$errors->all()" />

                                        @if (session()->has('pausa_em_minutos'))
                                            <x-botao disabled>Tente de novo em {{ session('pausa_em_minutos') }} min</x-botao>
                                        @else
                                            <x-botao data-test="login-button">Entrar</x-botao>
                                        @endif
                                    </form>

                                    <div class="mt-5 space-y-3 border-t border-coal/10 pt-5 text-center text-sm text-muted">
                                        <x-link discreto :href="route('acesso-por-link')">Entrar por link enviado por e-mail</x-link>
                                        <p>Ainda não tem conta? <x-link :href="route('register')">Criar conta grátis</x-link></p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <section id="entenda" class="py-20 sm:py-28">
                <div class="mx-auto grid w-[min(calc(100%-1.875rem),1240px)] items-center gap-12 sm:w-[min(calc(100%-3rem),1240px)] lg:grid-cols-[.93fr_1.07fr] lg:gap-16">
                    <div>
                        <p class="mb-[18px] text-[.72rem] font-black uppercase leading-none tracking-[.22em] text-muted">Não é só mais um minigame</p>
                        <h2 class="text-[clamp(3rem,5.7vw,5.4rem)] font-black leading-[.93] tracking-[-0.065em]">
                            A partida acaba.
                            <span class="mt-3 block w-fit max-w-full border-b-[6px] border-coral pb-1">A história fica.</span>
                        </h2>
                        <p class="mt-6 max-w-[630px] text-[1.03rem] leading-[1.75] text-muted">
                            No RANÇO, cada grupo tem uma <strong class="font-bold text-coal">mesa</strong>. É nela que ficam os jogadores,
                            as partidas, os pontos, as colocações e o histórico da competição.
                        </p>
                        <p class="mt-5 max-w-[630px] text-[1.03rem] leading-[1.75] text-muted">
                            Nesta edição, vocês jogam o Labirinto, rodada após rodada.
                            <strong class="font-bold text-coal">As partidas passam. A disputa continua.</strong>
                        </p>
                    </div>

                    <article class="overflow-hidden rounded-[30px] border border-coal/10 bg-white shadow-[0_28px_80px_rgba(32,32,31,.15)]">
                        <div class="flex items-start justify-between gap-4 bg-coal px-6 py-6 text-cream">
                            <div>
                                <p class="mb-1.5 text-[.64rem] font-black uppercase tracking-[.13em] text-cream/50">Mesa ativa</p>
                                <h3 class="text-[1.65rem] font-black tracking-[-0.04em]">Sexta sem produtividade</h3>
                            </div>
                            <span class="shrink-0 rounded-full bg-coral px-2.5 py-1.5 text-[.67rem] font-black uppercase text-coal">6 jogadores</span>
                        </div>
                        <div class="px-6 pb-6 pt-4">
                            @foreach ([
                                ['1º', 'Renata', '4 vitórias', '84 pts', true],
                                ['2º', 'Caio', '3 vitórias', '71 pts', false],
                                ['3º', 'Fabio', '2 vitórias', '63 pts', false],
                                ['4º', 'Juliana', 'Está tentando', '51 pts', false],
                            ] as [$lugar, $nome, $detalhe, $pontos, $primeiro])
                                <div @class(['grid grid-cols-[44px_1fr_auto] items-center gap-3 py-3', 'border-b border-coal/10' => ! $loop->last])>
                                    <div @class(['grid size-9 place-items-center rounded-xl font-black', 'bg-coral' => $primeiro, 'bg-sand' => ! $primeiro])>{{ $lugar }}</div>
                                    <div>
                                        <strong class="block text-[.94rem]">{{ $nome }}</strong>
                                        <span class="mt-0.5 block text-[.76rem] text-muted">{{ $detalhe }}</span>
                                    </div>
                                    <div class="font-black">{{ $pontos }}</div>
                                </div>
                            @endforeach
                            <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
                                <div class="rounded-2xl bg-sand p-3.5">
                                    <b class="block text-[.69rem] uppercase tracking-[.06em]">12 partidas jogadas</b>
                                    <span class="mt-1 block text-[.82rem] text-coal/70">O histórico continua crescendo.</span>
                                </div>
                                <div class="rounded-2xl bg-sand p-3.5">
                                    <b class="block text-[.69rem] uppercase tracking-[.06em]">Última partida</b>
                                    <span class="mt-1 block text-[.82rem] text-coal/70">Labirinto · Renata venceu.</span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="bg-sand py-20 sm:py-28">
                <div class="mx-auto w-[min(calc(100%-1.875rem),1240px)] sm:w-[min(calc(100%-3rem),1240px)]">
                    <div class="mb-12 max-w-[780px]">
                        <p class="mb-[18px] text-[.72rem] font-black uppercase leading-none tracking-[.22em] text-coal/70">Junte quem você gosta</p>
                        <h2 class="text-[clamp(2.7rem,5.4vw,4.8rem)] font-black leading-[.96] tracking-[-0.06em]">E descubra quem vai querer revanche primeiro.</h2>
                        <p class="mt-5 max-w-[700px] text-[1.02rem] leading-[1.7] text-coal/70">
                            O RANÇO não é sobre jogar sozinho e fechar a página. É sobre criar uma disputa que pertence ao seu grupo
                            e continuar de onde vocês pararam.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ([
                            ['01', 'Monte sua mesa', 'Família, amigos, casal ou colegas. Cada grupo tem seu próprio espaço de competição.', 'bg-cream text-coal', 'text-muted'],
                            ['02', 'Chame a turma', 'Convide pessoas por e-mail e reúna todo mundo na mesma mesa.', 'bg-coral text-coal', 'text-coal/70'],
                            ['03', 'Jogue coisas diferentes', 'Cada partida pode ser um desafio diferente. O grupo permanece o mesmo.', 'bg-cream text-coal', 'text-muted'],
                            ['04', 'Construa o histórico', 'Pontos, colocações, vitórias e derrotas ficam registrados para futuras provocações.', 'bg-coal text-cream', 'text-cream/60'],
                        ] as [$numero, $titulo, $texto, $fundo, $corpo])
                            <article @class(['min-h-60 rounded-[26px] border border-coal/10 p-6', $fundo])>
                                <div class="text-[3.8rem] font-black leading-[.9] tracking-[-0.08em] opacity-20">{{ $numero }}</div>
                                <h3 class="mb-2.5 mt-10 text-[1.4rem] font-black tracking-[-0.04em]">{{ $titulo }}</h3>
                                <p @class(['text-[.9rem] leading-relaxed', $corpo])>{{ $texto }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="jogos" class="py-20 sm:py-28">
                <div class="mx-auto w-[min(calc(100%-1.875rem),1240px)] sm:w-[min(calc(100%-3rem),1240px)]">
                    <div class="mb-12 max-w-[780px]">
                        <p class="mb-[18px] text-[.72rem] font-black uppercase leading-none tracking-[.22em] text-muted">A competição ganha forma</p>
                        <h2 class="text-[clamp(2.7rem,5.4vw,4.8rem)] font-black leading-[.96] tracking-[-0.06em]">Hoje é labirinto. E o ranking não esquece.</h2>
                        <p class="mt-5 max-w-[700px] text-[1.02rem] leading-[1.7] text-muted">
                            Simples e rápido. Cada partida gera um resultado,
                            e esse resultado volta para a mesma mesa.
                        </p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <article class="flex h-full flex-col overflow-hidden rounded-[30px] border border-coal/10 bg-white transition hover:-translate-y-1 hover:shadow-[0_28px_80px_rgba(32,32,31,.15)]">
                            <div class="grid h-[250px] shrink-0 place-items-center overflow-hidden bg-sand p-8" aria-hidden="true">
                                <img src="{{ asset('images/jogos/labirinto.svg') }}" alt="" class="size-36 object-contain text-coal">
                            </div>
                            <div class="flex min-h-[205px] flex-1 flex-col p-6">
                                <p class="mb-2 text-[.65rem] font-black uppercase tracking-[.12em] text-muted">Mapa + precisão</p>
                                <h3 class="text-[1.6rem] font-black tracking-[-0.045em]">Labirinto</h3>
                                <p class="mt-2.5 text-[.91rem] leading-relaxed text-muted">Escolha um mapa, encontre a saída e tente baixar seu tempo.</p>
                            </div>
                        </article>

                        <article class="flex h-full flex-col overflow-hidden rounded-[30px] border border-coal/10 bg-white transition hover:-translate-y-1 hover:shadow-[0_28px_80px_rgba(32,32,31,.15)]">
                            <div class="grid h-[250px] shrink-0 place-items-center overflow-hidden bg-coal p-8 text-cream" aria-hidden="true">
                                <p class="text-center text-[1.4rem] font-black leading-tight tracking-[-0.04em]">Crie uma mesa.<br>Chame a galera.</p>
                            </div>
                            <div class="flex min-h-[205px] flex-1 flex-col p-6">
                                <p class="mb-2 text-[.65rem] font-black uppercase tracking-[.12em] text-muted">Jogo de mesa</p>
                                <h3 class="text-[1.6rem] font-black tracking-[-0.045em]">Labirinto em mesa</h3>
                                <p class="mt-2.5 text-[.91rem] leading-relaxed text-muted">Todos percorrem o mesmo labirinto na rodada. Quem faz o menor tempo pontua, e o ranking acompanha a mesa.</p>
                                @if ($entrou)
                                    <a href="{{ route('mesas.formulario') }}" class="mt-4 inline-flex min-h-10 items-center rounded-xl bg-coal px-4 text-xs font-black uppercase tracking-[.08em] text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Criar uma mesa</a>
                                @endif
                            </div>
                        </article>
                    </div>

                    <p class="mt-7 text-center text-base font-black">
                        Cada partida conta. <span class="underline decoration-coral decoration-[3px] underline-offset-4">A mesma mesa. O mesmo ranking.</span>
                    </p>
                </div>
            </section>

            <section id="exemplo" class="bg-coal py-20 text-cream sm:py-28">
                <div class="mx-auto grid w-[min(calc(100%-1.875rem),1240px)] items-start gap-12 sm:w-[min(calc(100%-3rem),1240px)] lg:grid-cols-[.8fr_1.2fr] lg:gap-16">
                    <div>
                        <p class="mb-[18px] text-[.72rem] font-black uppercase leading-none tracking-[.22em] text-coral">Uma sexta-feira qualquer</p>
                        <h2 class="text-[clamp(3rem,5.5vw,5rem)] font-black leading-[.94] tracking-[-0.065em]">As partidas passam. A disputa continua.</h2>
                        <p class="mt-6 max-w-[520px] leading-[1.75] text-cream/60">
                            Seis colegas resolvem jogar. Criam uma mesa, fazem uma partida e vão embora.
                            Na semana seguinte, voltam e jogam de novo.
                        </p>
                        <p class="mt-5 max-w-[520px] leading-[1.75] text-cream/60">
                            A diferença é que o RANÇO não esqueceu nada.
                            <strong class="font-bold text-cream">O ranking continua exatamente de onde parou.</strong>
                        </p>
                    </div>

                    <div class="grid gap-3.5">
                        <article class="rounded-[22px] border border-cream/10 bg-cream/[.06] p-5">
                            <p class="text-[.63rem] font-black uppercase tracking-[.13em] text-cream/40">Semana 1</p>
                            <h3 class="mb-4 mt-2 text-[1.4rem] font-black tracking-[-0.04em]">Labirinto</h3>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-cream/10 px-2.5 py-2 text-[.76rem]">Renata <strong class="text-coral">+10</strong></span>
                                <span class="rounded-full bg-cream/10 px-2.5 py-2 text-[.76rem]">Caio <strong class="text-coral">+7</strong></span>
                                <span class="rounded-full bg-cream/10 px-2.5 py-2 text-[.76rem]">Marcos <strong class="text-coral">+5</strong></span>
                            </div>
                        </article>
                        <p class="text-center text-[1.4rem] text-cream/30" aria-hidden="true">↓</p>
                        <article class="rounded-[22px] border border-cream/10 bg-cream/[.06] p-5">
                            <p class="text-[.63rem] font-black uppercase tracking-[.13em] text-cream/40">Semana 2</p>
                            <h3 class="mb-4 mt-2 text-[1.4rem] font-black tracking-[-0.04em]">Labirinto</h3>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-cream/10 px-2.5 py-2 text-[.76rem]">Marcos <strong class="text-coral">+10</strong></span>
                                <span class="rounded-full bg-cream/10 px-2.5 py-2 text-[.76rem]">Renata <strong class="text-coral">+7</strong></span>
                                <span class="rounded-full bg-cream/10 px-2.5 py-2 text-[.76rem]">Caio <strong class="text-coral">+5</strong></span>
                            </div>
                        </article>
                        <p class="text-center text-[1.4rem] text-cream/30" aria-hidden="true">↓</p>
                        <article class="rounded-[22px] bg-cream p-6 text-coal">
                            <p class="mb-3 text-[.64rem] font-black uppercase tracking-[.13em] text-muted">Ranking da mesa</p>
                            <div class="flex justify-between gap-5 border-b border-coal/10 py-2.5 font-extrabold"><span>1. Renata</span><b>17 pts</b></div>
                            <div class="flex justify-between gap-5 border-b border-coal/10 py-2.5 font-extrabold"><span>2. Marcos</span><b>15 pts</b></div>
                            <div class="flex justify-between gap-5 py-2.5 font-extrabold"><span>3. Caio</span><b>12 pts</b></div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="cta" class="relative overflow-hidden py-20 text-center sm:py-28">
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-32 size-64 rounded-full border-[36px] border-coral"></div>
                <div class="relative z-10 mx-auto w-[min(calc(100%-1.875rem),850px)] sm:w-[min(calc(100%-3rem),850px)]">
                    <p class="mb-[18px] text-[.72rem] font-black uppercase leading-none tracking-[.22em] text-muted">A entrada é grátis. A derrota também.</p>
                    <h2 class="text-[clamp(3.2rem,6vw,5.8rem)] font-black leading-[.92] tracking-[-0.07em]">Já sabe quem colocar na sua mesa?</h2>
                    <p class="mx-auto mt-5 max-w-[640px] text-base text-muted">
                        Uma disputa que vai render assunto.
                    </p>
                    @if ($entrou)
                        <a href="{{ route('dashboard') }}" class="mt-7 inline-flex min-h-[52px] items-center justify-center rounded-[14px] bg-coal px-6 text-[.8rem] font-black uppercase tracking-[.06em] text-cream transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Continuar</a>
                    @else
                        <a href="{{ route('register') }}" class="mt-7 inline-flex min-h-[52px] items-center justify-center rounded-[14px] bg-coal px-6 text-[.8rem] font-black uppercase tracking-[.06em] text-cream transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Criar conta grátis</a>
                    @endif
                </div>
            </section>
        </main>

        <footer class="border-t border-coal/10 bg-cream py-7">
            <div class="mx-auto flex w-[min(calc(100%-1.875rem),1240px)] flex-col justify-between gap-4 text-[.78rem] text-muted sm:w-[min(calc(100%-3rem),1240px)] sm:flex-row sm:items-center">
                <div>
                    <strong class="text-coal">RANÇO.</strong>
                    <p class="mt-2">Feito por <strong class="text-coal">Caio Costa</strong>.</p>
                    <p class="mt-1">
                        Reclamações sobre o jogo eu leio. Sobre perder, não prometo.
                    </p>
                </div>

                <a
                    href="mailto:contato@seu-dominio.exemplo"
                    class="rounded underline decoration-coal/30 underline-offset-4 transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coal"
                >Contato: contato@seu-dominio.exemplo</a>
            </div>
            <x-links-documentos class="mt-6 px-5" />
        </footer>

        @fluxScripts
    </body>
</html>
