<x-layouts::app title="Regras aceitas">
    <section class="w-full">
        @include('partials.settings-heading')
        <x-pages::settings.layout heading="Regras aceitas">
            <ul class="space-y-6">
                @foreach ($aceites as $aceite)
                    <li class="border-b border-coal/10 pb-6">
                        <p class="text-sm leading-6">Versão de {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $aceite->versao)->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y') }}, aceita em {{ $aceite->aceito_em->setTimezone('America/Sao_Paulo')->format('d/m/Y') }} às {{ $aceite->aceito_em->setTimezone('America/Sao_Paulo')->format('H:i') }}</p>
                        <x-links-documentos :versao="$aceite->versao" class="mt-3 justify-start" />
                    </li>
                @endforeach
            </ul>
        </x-pages::settings.layout>
    </section>
</x-layouts::app>
