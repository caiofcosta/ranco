@props(['versao' => null])
<!DOCTYPE html>
<html lang="pt-BR">
    <head>@include('partials.head')</head>
    <body class="min-h-screen bg-cream text-coal antialiased">
        <header class="mx-auto max-w-3xl px-5 py-8">
            <a href="{{ route('home') }}" class="rounded text-2xl font-black focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coal">RANÇO.</a>
        </header>
        <main class="mx-auto max-w-3xl px-5 pb-12">
            <x-mobile-breadcrumb :titulo="$title ?? null" />
            <article class="break-words text-base leading-7 [&_h1]:mb-6 [&_h1]:text-3xl [&_h1]:font-black [&_h2]:mb-3 [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-bold [&_p]:my-4 [&_ul]:list-disc [&_ul]:pl-6 [&_li]:my-2 [&_table]:block [&_table]:overflow-x-auto [&_td]:border [&_td]:border-coal/15 [&_td]:p-3 [&_th]:border [&_th]:border-coal/15 [&_th]:p-3 [&_a]:underline">
                {{ $slot }}
            </article>
        </main>
        <footer class="border-t border-coal/10 px-5 py-6"><x-links-documentos :versao="$versao" /></footer>
    </body>
</html>
