<x-filament-panels::page>
    <style>
        .disponibilidade-jogos {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 16px;
        }
        .disponibilidade-jogos__cartao {
            display: flex;
            min-width: 0;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px;
            border: 1px solid rgb(255 255 255 / 10%);
            border-radius: 12px;
            background: rgb(255 255 255 / 4%);
        }
        .disponibilidade-jogos__identidade {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 12px;
        }
        .disponibilidade-jogos__logo {
            width: 48px;
            height: 48px;
            flex: 0 0 auto;
            object-fit: contain;
        }
        .disponibilidade-jogos__nome {
            color: #f9fafb;
            font-weight: 700;
        }
        .disponibilidade-jogos__estado {
            margin-top: 4px;
            color: #a1a1aa;
            font-size: 0.875rem;
        }
        .disponibilidade-jogos__botao {
            flex: 0 0 auto;
            border: 0;
            border-radius: 8px;
            padding: 9px 13px;
            background: rgb(255 255 255 / 10%);
            color: #f9fafb;
            font: inherit;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
        }
        .disponibilidade-jogos__botao--ativar {
            background: #ff684d;
            color: #20201f;
        }
        .disponibilidade-jogos__botao:focus-visible {
            outline: 2px solid #ff684d;
            outline-offset: 3px;
        }
        .disponibilidade-jogos__botao:disabled {
            cursor: wait;
            opacity: 0.65;
        }
        .disponibilidade-jogos__vazio {
            color: #a1a1aa;
        }
        @media (min-width: 640px) {
            .disponibilidade-jogos {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1280px) {
            .disponibilidade-jogos {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
    </style>

    <div class="disponibilidade-jogos">
        @forelse ($jogos as $jogo)
            <section class="disponibilidade-jogos__cartao">
                <div class="disponibilidade-jogos__identidade">
                    <img src="{{ asset($jogo['logo']) }}" alt="" class="disponibilidade-jogos__logo">
                    <div>
                        <h2 class="disponibilidade-jogos__nome">{{ $jogo['nome'] }}</h2>
                        <p class="disponibilidade-jogos__estado">{{ $jogo['ativo'] ? 'Ativo' : 'Inativo' }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="alternar('{{ $jogo['id'] }}')"
                    wire:loading.attr="disabled"
                    aria-pressed="{{ $jogo['ativo'] ? 'true' : 'false' }}"
                    class="disponibilidade-jogos__botao {{ $jogo['ativo'] ? '' : 'disponibilidade-jogos__botao--ativar' }}"
                >
                    {{ $jogo['ativo'] ? 'Inativar' : 'Ativar' }}
                </button>
            </section>
        @empty
            <p class="disponibilidade-jogos__vazio">Nenhum jogo cadastrado.</p>
        @endforelse
    </div>
</x-filament-panels::page>
