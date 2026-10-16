<x-filament-panels::page>
    <style>
        .usuarios-online {
            display: grid;
            gap: 12px;
        }
        .usuarios-online__resumo {
            margin-bottom: 4px;
            color: #a1a1aa;
            font-size: 0.875rem;
        }
        .usuarios-online__linha {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 4px 16px;
            padding: 16px 20px;
            border: 1px solid rgb(255 255 255 / 10%);
            border-radius: 12px;
            background: rgb(255 255 255 / 4%);
        }
        .usuarios-online__nome {
            color: #f9fafb;
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .usuarios-online__email,
        .usuarios-online__atividade,
        .usuarios-online__vazio {
            color: #a1a1aa;
            font-size: 0.875rem;
            overflow-wrap: anywhere;
        }
        .usuarios-online__atividade {
            margin-top: 4px;
        }
        .usuarios-online__paginacao {
            margin-top: 8px;
        }
        @media (min-width: 700px) {
            .usuarios-online__linha {
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
            }
            .usuarios-online__atividade {
                grid-column: 1 / -1;
            }
        }
    </style>

    <div class="usuarios-online" wire:poll.30s>
        <p class="usuarios-online__resumo">Atividade registrada nos últimos cinco minutos: {{ $usuarios->total() }}</p>

        @forelse ($usuarios as $usuario)
            <article class="usuarios-online__linha" wire:key="usuario-online-{{ $usuario->id }}">
                <h2 class="usuarios-online__nome">{{ $usuario->name }}</h2>
                <p class="usuarios-online__email">{{ $usuario->email }}</p>
                <p class="usuarios-online__atividade">
                    Última atividade: {{ \Illuminate\Support\Carbon::createFromTimestamp((int) $usuario->ultima_atividade)->diffForHumans() }}
                </p>
            </article>
        @empty
            <p class="usuarios-online__vazio">Nenhuma sessão de usuário ativa neste momento.</p>
        @endforelse

        <div class="usuarios-online__paginacao">{{ $usuarios->links() }}</div>
    </div>
</x-filament-panels::page>
