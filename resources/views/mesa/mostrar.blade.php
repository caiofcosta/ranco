<x-layouts::app :title="$nome" :tela-cheia="true">
    <livewire:mesa.mostrar-mesa :id="$id" :resultado="$resultado" />
    @vite('resources/js/mesa/tempo-real.js')
</x-layouts::app>
