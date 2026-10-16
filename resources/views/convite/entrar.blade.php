<x-layouts::app title="Convite">
    <div class="max-w-lg">
        <p class="mb-2 text-[.63rem] font-black uppercase tracking-[.16em] text-muted">Convite de mesa</p>

        @error('convite')
            <p class="mb-6 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
        @enderror

        @if ($estado === 'valido')
            <h1 class="text-[clamp(1.8rem,3vw,2.7rem)] font-black leading-none tracking-[-0.05em]">{{ $nomeConvidante }} te convidou para uma mesa.</h1>
            <p class="mt-4 text-lg font-bold">{{ $nomeMesa }}</p>
            <p class="mt-2 text-sm text-muted">Tem gente esperando você por lá.</p>
            <form method="POST" action="{{ $urlAceitar }}" class="mt-8">
                @csrf
                <x-botao data-test="entrar-na-mesa">Entrar na mesa</x-botao>
            </form>
            <p class="mt-6 text-center text-sm text-muted">
                <x-link :href="route('dashboard')" discreto>Agora não</x-link>
            </p>
        @elseif ($estado === 'proprio')
            <h1 class="text-[clamp(1.8rem,3vw,2.7rem)] font-black leading-none tracking-[-0.05em]">Esse convite é seu.</h1>
            <p class="mt-4 text-sm text-muted">Ele serve para quem recebeu o e-mail.</p>
            <p class="mt-6"><x-link :href="route('dashboard')">Ir para o Início</x-link></p>
        @elseif ($estado === 'alheio')
            <h1 class="text-[clamp(1.8rem,3vw,2.7rem)] font-black leading-none tracking-[-0.05em]">Este convite não é para esta conta.</h1>
            <p class="mt-6"><x-link :href="route('dashboard')">Ir para o Início</x-link></p>
        @elseif ($estado === 'encerrado')
            <h1 class="text-[clamp(1.8rem,3vw,2.7rem)] font-black leading-none tracking-[-0.05em]">A mesa foi encerrada.</h1>
            <p class="mt-6"><x-link :href="route('dashboard')">Ir para o Início</x-link></p>
        @else
            <h1 class="text-[clamp(1.8rem,3vw,2.7rem)] font-black leading-none tracking-[-0.05em]">Este convite não vale mais.</h1>
            <p class="mt-6"><x-link :href="route('dashboard')">Ir para o Início</x-link></p>
        @endif
    </div>
</x-layouts::app>
