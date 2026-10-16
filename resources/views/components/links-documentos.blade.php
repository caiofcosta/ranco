@props(['versao' => null])
<nav aria-label="Documentos do RANÇO"
    {{ $attributes->class('flex flex-wrap justify-center gap-x-4 gap-y-2 text-sm') }}>
    <x-link :href="route('documentos.termos', ['versao' => $versao])" :discreto="true" target="_blank" rel="noopener">Termos de Uso</x-link>
    <x-link :href="route('documentos.privacidade', ['versao' => $versao])" :discreto="true" target="_blank" rel="noopener">Política de Privacidade</x-link>
    <x-link :href="route('documentos.conduta', ['versao' => $versao])" :discreto="true" target="_blank" rel="noopener">Regras de Conduta</x-link>
</nav>
