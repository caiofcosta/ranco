<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
@auth
    {{-- Canal privado da própria conta (avisos como "ficou invisível" para as outras abas). --}}
    <meta name="pessoa-id" content="{{ auth()->user()->identificadorNoDominio() }}" />
@endauth

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

{{-- Ao trocar o ícone, aumente o v=: o Cloudflare e o navegador guardam o arquivo por horas. --}}
<link rel="icon" href="/favicon.ico?v=2" sizes="any">
<link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
{{-- A casca é só tema claro. Com .dark, o Flux pinta o texto de branco e ele some no marfim. --}}
<script>
    window.Flux.applyAppearance('light');
    window.Flux.applyAppearance = function () {
        document.documentElement.classList.remove('dark');
        window.localStorage.setItem('flux.appearance', 'light');
    };
</script>
