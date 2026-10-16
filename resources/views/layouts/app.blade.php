<x-layouts::app.sidebar :title="$title ?? null" :cabecalho="$cabecalho ?? null" :tela-cheia="$telaCheia ?? false">
    {{ $slot }}
</x-layouts::app.sidebar>
