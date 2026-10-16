{{--
    Telas de acesso (variação B da identidade visual): painel editorial grafite com a marca e a
    promessa, e o formulário num cartão branco. Sem instruções de uso: a tela se explica sozinha.
--}}
@props(['sobrio' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-cream text-coal antialiased">
        <main @class(['min-h-svh', 'lg:grid lg:grid-cols-[.9fr_1.1fr]' => ! $sobrio])>
            @unless ($sobrio)
            <section class="relative overflow-hidden bg-coal px-6 py-8 text-cream sm:px-10 lg:flex lg:min-h-svh lg:flex-col lg:justify-between lg:px-14 lg:py-12">
                <div aria-hidden="true" class="absolute -right-20 -top-20 size-48 rounded-full border-[32px] border-coral opacity-90 lg:-right-24 lg:-top-24 lg:size-72 lg:border-[45px]"></div>

                <header class="relative z-10">
                    <a href="{{ route('home') }}" class="rounded text-3xl font-black tracking-[-0.07em] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral">RANÇO.</a>
                </header>

                <div class="relative z-10 mt-10 max-w-xl lg:my-0">
                    <p class="mb-6 hidden text-xs font-bold uppercase tracking-[.22em] text-cream/60 sm:block">competições absolutamente necessárias*</p>
                    <p class="text-4xl font-black leading-[.93] tracking-[-.065em] sm:text-6xl xl:text-7xl">
                        Entre.<br>Jogue.<br><span class="text-coral">Arrume assunto.</span>
                    </p>
                    <p class="mt-8 hidden max-w-md text-base leading-7 text-cream/70 sm:block">
                        Algumas amizades rendem boas histórias. Outras ainda não jogaram RANÇO.
                    </p>
                </div>

                <footer class="relative z-10 mt-10 hidden sm:block lg:mt-0">
                    <p class="text-xs text-cream/60">* provavelmente não.</p>
                </footer>
            </section>

            @endunless

            <section class="flex items-center justify-center px-5 py-10 sm:px-10 lg:min-h-svh">
                <div class="w-full max-w-lg rounded-[32px] border border-coal/[.07] bg-white p-6 shadow-[0_30px_90px_rgba(26,23,20,.14)] sm:p-9">
                    @if ($sobrio)
                        <a href="{{ route('home') }}" class="mb-8 inline-block rounded text-2xl font-black tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coal">RANÇO.</a>
                    @endif
                    <x-mobile-breadcrumb :titulo="$title ?? null" />
                    {{ $slot }}
                </div>
            </section>
        </main>
        <footer class="border-t border-coal/10 px-5 py-6"><x-links-documentos /></footer>

        @fluxScripts
    </body>
</html>
