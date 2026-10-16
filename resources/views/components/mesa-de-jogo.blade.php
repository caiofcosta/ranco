{{-- Tampo adaptado à quantidade de cadeiras; o conteúdo do centro vem no slot. Estilos em resources/css/mesa-de-jogo.css. --}}
@props(['cadeiras', 'podeRemover' => false])

<div {{ $attributes->class('mesa-de-jogo') }} data-lugares="{{ count($cadeiras) }}">
    <div class="mesa-de-jogo__palco">
        <div class="mesa-de-jogo__tampo">
            <div class="max-w-xs">{{ $slot }}</div>
        </div>

        @foreach ($cadeiras as $cadeira)
            @php
                // Cadeira de um participante que o admin pode remover: nunca o próprio admin, nem
                // convite pendente ou vaga. A regra real fica em Mesa::removerDaMesa.
                $removivel = $podeRemover && $cadeira['tipo'] === 'pessoa' && $cadeira['id'] && ! $cadeira['admin'];
                $decorativa = $cadeira['tipo'] === 'vaga' && $cadeira['url'] === null;
                $descricao = match ($cadeira['tipo']) {
                    'voce' => 'Você',
                    'convite' => 'Convite enviado para '.$cadeira['nome'].'. Clique para ver o status e gerenciar o convite.',
                    'vaga' => 'Lugar livre: convidar',
                    default => $cadeira['nome'],
                };
                if ($cadeira['admin']) {
                    $descricao .= ', admin da mesa';
                }
                if ($cadeira['local']) {
                    $descricao .= ', participante local';
                }
                if ($cadeira['marca']) {
                    $descricao .= ', '.mb_strtolower($cadeira['marca']);
                }
                if ($removivel) {
                    $descricao .= '. Toque para gerenciar.';
                }
                $tag = $cadeira['url'] ? 'a' : ($removivel ? 'button' : 'div');
            @endphp
            <{{ $tag }}
                class="cadeira"
                data-posicao="{{ $cadeira['posicao'] }}"
                data-tipo="{{ $cadeira['tipo'] }}"
                @if ($cadeira['id']) data-cadeira="{{ $cadeira['id'] }}" @endif
                @if ($cadeira['url']) href="{{ $cadeira['url'] }}" @endif
                @if ($removivel)
                    type="button"
                    data-test="cadeira-removivel"
                    x-on:click="$dispatch('abrir-remocao', { id: @js((string) $cadeira['id']), nome: @js((string) $cadeira['nome']), local: @js((bool) $cadeira['local']), foto: @js($cadeira['foto']), iniciais: @js($cadeira['iniciais']) })"
                @endif
                @if ($decorativa) aria-hidden="true" @else aria-label="{{ $descricao }}" @endif
                @if ($cadeira['nome']) title="{{ $cadeira['nome'] }}" @endif
                @if ($cadeira['tipo'] === 'convite') data-status-convite="pendente" @endif
            >
                @if ($cadeira['admin'])
                    <span class="cadeira__coroa" aria-hidden="true">♛</span>
                @endif
                <span class="cadeira__encosto" aria-hidden="true">
                    @if ($cadeira['tipo'] === 'vaga')
                        <span class="cadeira__assento">+</span>
                    @else
                        <x-avatar :nome="$cadeira['nome'] ?? ''" :foto="$cadeira['foto'] ?? null" class="cadeira__assento" />
                    @endif
                    @if ($cadeira['marca'])
                        <span class="cadeira__marca">{{ $cadeira['marca'] }}</span>
                    @endif
                </span>
                <span class="cadeira__nome" aria-hidden="true">{{ $cadeira['rotulo'] }}</span>
                @if ($cadeira['local'])
                    <span class="cadeira__selo" aria-hidden="true">local</span>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</div>
