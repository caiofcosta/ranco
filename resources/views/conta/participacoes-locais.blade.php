<x-layouts::app title="Participações locais">
    <x-pages::settings.layout heading="Participações locais" subheading="">
        @if (session('status'))
            <x-aviso class="mb-6">{{ session('status') }}</x-aviso>
        @endif

        @error('participacao')
            <p class="mb-6 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
        @enderror

        @if ($pendentes === [])
            <p class="text-sm text-muted">Nenhuma participação pendente.</p>
        @else
            <x-aviso class="mb-6">Reconhecer inclui a participação no histórico da conta.</x-aviso>

            <ul class="grid gap-2">
                @foreach ($pendentes as $pendente)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3">
                        <span>
                            <span class="block font-bold">{{ $pendente->nome }}</span>
                            <span class="text-sm text-muted">{{ $pendente->mesa }}</span>
                        </span>
                        <span class="flex items-center gap-4">
                            <form method="POST" action="{{ route('conta.participacoes-locais.rejeitar', $pendente->id) }}">
                                @csrf
                                <button type="submit" class="rounded text-sm font-semibold text-muted transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="rejeitar-participacao-local">Rejeitar</button>
                            </form>
                            <form method="POST" action="{{ route('conta.participacoes-locais.reconhecer', $pendente->id) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-10 items-center rounded-full bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="reconhecer-participacao-local">Reconhecer</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-pages::settings.layout>
</x-layouts::app>
