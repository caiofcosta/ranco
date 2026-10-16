<x-filament-panels::page>
    <style>
        .buscar-usuario {
            display: grid;
            gap: 20px;
        }
        .buscar-usuario__formulario {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }
        .buscar-usuario__campo {
            display: grid;
            flex: 1 1 280px;
            gap: 6px;
        }
        .buscar-usuario__rotulo {
            color: #f9fafb;
            font-weight: 600;
        }
        .buscar-usuario__entrada {
            width: 100%;
            min-height: 42px;
            border: 1px solid rgb(255 255 255 / 25%);
            border-radius: 8px;
            padding: 8px 12px;
            background: #20201f;
            color: #f9fafb;
        }
        .buscar-usuario__entrada:focus-visible,
        .buscar-usuario__botao:focus-visible,
        .buscar-usuario__resultado:focus-visible {
            outline: 2px solid #ff684d;
            outline-offset: 3px;
        }
        .buscar-usuario__ajuda,
        .buscar-usuario__vazio,
        .buscar-usuario__resumo,
        .buscar-usuario__paginacao {
            color: #a1a1aa;
            font-size: 0.875rem;
        }
        .buscar-usuario__resultados,
        .usuario-detalhes__grade,
        .usuario-detalhes__lista {
            display: grid;
            gap: 10px;
        }
        .buscar-usuario__resultado,
        .usuario-detalhes__secao,
        .usuario-detalhes__item {
            border: 1px solid rgb(255 255 255 / 10%);
            border-radius: 12px;
            padding: 16px 20px;
            background: rgb(255 255 255 / 4%);
        }
        .buscar-usuario__resultado {
            display: flex;
            width: 100%;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 4px 16px;
            color: #f9fafb;
            text-align: left;
            cursor: pointer;
        }
        .buscar-usuario__nome,
        .usuario-detalhes__nome {
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .buscar-usuario__email,
        .usuario-detalhes__valor {
            color: #d4d4d8;
            font-size: 0.9rem;
            overflow-wrap: anywhere;
        }
        .buscar-usuario__paginacao {
            margin-top: 8px;
        }
        .usuario-detalhes__fundo {
            position: fixed;
            z-index: 100;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgb(0 0 0 / 72%);
        }
        .usuario-detalhes {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            width: min(100%, 900px);
            max-height: min(90vh, 900px);
            overflow: hidden;
            border: 1px solid rgb(255 255 255 / 15%);
            border-radius: 16px;
            background: #171717;
            color: #f9fafb;
            box-shadow: 0 24px 80px rgb(0 0 0 / 55%);
        }
        .usuario-detalhes__cabecalho {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 24px;
            border-bottom: 1px solid rgb(255 255 255 / 10%);
        }
        .usuario-detalhes__titulo {
            font-size: 1.25rem;
            font-weight: 800;
            overflow-wrap: anywhere;
        }
        .usuario-detalhes__subtitulo,
        .usuario-detalhes__rotulo,
        .usuario-detalhes__nota {
            color: #a1a1aa;
            font-size: 0.85rem;
        }
        .usuario-detalhes__conteudo {
            display: grid;
            min-height: 0;
            gap: 16px;
            overflow-y: scroll;
            padding: 20px 24px 24px;
            scrollbar-color: #737373 #262626;
            scrollbar-width: thin;
        }
        .usuario-detalhes__secao {
            display: grid;
            gap: 12px;
        }
        .usuario-detalhes__secao h3 {
            font-size: 1rem;
            font-weight: 750;
        }
        .usuario-detalhes__grade {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr));
        }
        .usuario-detalhes__item {
            display: grid;
            gap: 4px;
            padding: 12px 14px;
        }
        .usuario-detalhes__linha {
            display: grid;
            gap: 3px;
            padding: 10px 0;
            border-top: 1px solid rgb(255 255 255 / 8%);
        }
        .usuario-detalhes__linha:first-child {
            border-top: 0;
            padding-top: 0;
        }
        .usuario-detalhes__botao {
            flex: 0 0 auto;
            border: 1px solid rgb(255 255 255 / 25%);
            border-radius: 8px;
            padding: 8px 12px;
            color: #f9fafb;
            font: inherit;
            cursor: pointer;
        }
        .usuario-detalhes__botao--perigo {
            border-color: #dc2626;
            background: #7f1d1d;
        }
        .usuario-detalhes__botao--perigo:hover {
            background: #991b1b;
        }
        .usuario-detalhes__acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .usuario-detalhes__botao:focus-visible {
            outline: 2px solid #ff684d;
            outline-offset: 3px;
        }
        @media (max-width: 600px) {
            .usuario-detalhes__fundo {
                padding: 8px;
            }
            .usuario-detalhes {
                max-height: 96vh;
            }
            .usuario-detalhes__cabecalho,
            .usuario-detalhes__conteudo {
                padding-right: 16px;
                padding-left: 16px;
            }
        }
    </style>

    <div class="buscar-usuario" wire:keydown.escape.window="fecharModal">
        <div class="buscar-usuario__formulario">
            <label class="buscar-usuario__campo">
                <span class="buscar-usuario__rotulo">Nome ou e-mail</span>
                <input
                    class="buscar-usuario__entrada"
                    type="search"
                    wire:model.live.debounce.300ms="busca"
                    autocomplete="off"
                    placeholder="Filtrar por nome ou e-mail"
                >
            </label>
        </div>

        <p class="buscar-usuario__resumo">
            @if (trim($busca) !== '')
                {{ $resultados->total() === 1 ? '1 usuário encontrado' : $resultados->total().' usuários encontrados' }}
            @else
                {{ $resultados->total() === 1 ? '1 usuário cadastrado' : $resultados->total().' usuários cadastrados' }}
            @endif
        </p>

        <div class="buscar-usuario__resultados" aria-live="polite">
            @forelse ($resultados as $resultado)
                <article class="buscar-usuario__resultado" wire:key="resultado-usuario-{{ $resultado->id }}">
                    <button
                        class="buscar-usuario__nome"
                        type="button"
                        wire:click="selecionar({{ $resultado->id }})"
                        aria-haspopup="dialog"
                    >
                        {{ $resultado->name }}
                    </button>
                    <span class="buscar-usuario__email">{{ $resultado->email }}</span>
                </article>
            @empty
                <p class="buscar-usuario__vazio">Nenhum usuário encontrado.</p>
            @endforelse
        </div>

        <div class="buscar-usuario__paginacao">{{ $resultados->links() }}</div>

        @if ($detalhes)
            <div class="usuario-detalhes__fundo" wire:click.self="fecharModal">
                <section
                    class="usuario-detalhes"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="usuario-detalhes-titulo"
                    tabindex="-1"
                >
                    <header class="usuario-detalhes__cabecalho">
                        <div>
                            <h2 id="usuario-detalhes-titulo" class="usuario-detalhes__titulo">{{ $detalhes['conta']['nome'] }}</h2>
                            <p class="usuario-detalhes__subtitulo">{{ $detalhes['conta']['email'] }} · Conta #{{ $detalhes['conta']['id'] }}</p>
                        </div>
                        <button class="usuario-detalhes__botao" type="button" wire:click="fecharModal">Fechar</button>
                    </header>

                    <div class="usuario-detalhes__conteudo">
                        <section class="usuario-detalhes__secao">
                            <h3>Conta</h3>
                            <div class="usuario-detalhes__grade">
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">Criada em</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['criadaEm'] ?? '—' }}</span></div>
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">E-mail confirmado</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['emailConfirmadoEm'] ?? 'Não confirmado' }}</span></div>
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">Invisível para amigos</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['invisivel'] ? 'Sim' : 'Não' }}</span></div>
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">Acesso ao sistema</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['bloqueadoEm'] ? 'Bloqueado em '.$detalhes['conta']['bloqueadoEm'] : 'Liberado' }}</span></div>
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">Último visto no sistema</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['vistaEm'] ?? 'Sem registro' }}</span></div>
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">Senha configurada</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['senhaConfigurada'] ? 'Sim' : 'Não' }}</span></div>
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">Google vinculado</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['googleVinculado'] ? 'Sim' : 'Não' }}</span></div>
                                <div class="usuario-detalhes__item"><span class="usuario-detalhes__rotulo">Autenticação em dois fatores</span><span class="usuario-detalhes__valor">{{ $detalhes['conta']['autenticacaoEmDoisFatores'] ? 'Ativada' : 'Desativada' }}</span></div>
                            </div>
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Ações administrativas</h3>
                            @if ($podeAdministrarUsuario)
                                <div class="usuario-detalhes__acoes">
                                    @if ($detalhes['conta']['bloqueadoEm'])
                                        <button class="usuario-detalhes__botao" type="button" wire:click="desbloquearUsuario">
                                            Desbloquear conta
                                        </button>
                                    @else
                                        <button
                                            class="usuario-detalhes__botao"
                                            type="button"
                                            wire:click="bloquearUsuario"
                                            wire:confirm="Bloquear esta conta e encerrar todas as sessões abertas?"
                                        >
                                            Bloquear conta
                                        </button>
                                    @endif
                                    <button
                                        class="usuario-detalhes__botao usuario-detalhes__botao--perigo"
                                        type="button"
                                        wire:click="excluirUsuario"
                                        wire:confirm="Excluir permanentemente esta conta? As mesas abertas que ela administra serão encerradas sem vencedor; mesas e histórico compartilhados serão preservados, inclusive nomes já gravados. Registros de acesso serão mantidos pelo prazo de retenção."
                                    >
                                        Excluir conta
                                    </button>
                                </div>
                            @else
                                <p class="usuario-detalhes__nota">A própria conta e as contas de administradores do sistema não podem ser alteradas por aqui.</p>
                            @endif
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Aceites</h3>
                            @forelse ($detalhes['aceites'] as $aceite)
                                <p class="usuario-detalhes__valor">Versão {{ $aceite['versao'] }} · {{ $aceite['aceitoEm'] ?? 'Sem data' }}</p>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhum aceite registrado.</p>
                            @endforelse
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Acessos recentes</h3>
                            @forelse ($detalhes['acessos'] as $acesso)
                                <p class="usuario-detalhes__valor">{{ $acesso }}</p>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhum acesso registrado.</p>
                            @endforelse
                            <p class="usuario-detalhes__nota">Até 20 registros recentes. IP e porta não são exibidos.</p>
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Sessões no banco</h3>
                            @forelse ($detalhes['sessoes'] as $sessao)
                                <p class="usuario-detalhes__valor">{{ $sessao }}</p>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhuma sessão persistida.</p>
                            @endforelse
                            <p class="usuario-detalhes__nota">Horários da atividade mais recente de até 10 sessões; identificadores e dados técnicos são omitidos.</p>
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Amigos e relações</h3>
                            @foreach ([
                                'Amigos' => $detalhes['amizades']['amigos'],
                                'Pedidos enviados' => $detalhes['amizades']['pedidosEnviados'],
                                'Pedidos recebidos' => $detalhes['amizades']['pedidosRecebidos'],
                                'Bloqueados pela conta' => $detalhes['amizades']['bloqueadosPorConta'],
                                'Contas que bloquearam' => $detalhes['amizades']['bloquearamConta'],
                            ] as $titulo => $relacoes)
                                <div>
                                    <h4 class="usuario-detalhes__rotulo">{{ $titulo }} ({{ count($relacoes) }})</h4>
                                    @forelse ($relacoes as $relacao)
                                        <p class="usuario-detalhes__valor">{{ $relacao['nome'] }}{{ $relacao['email'] ? ' · '.$relacao['email'] : '' }}{{ $relacao['criadaEm'] ? ' · '.$relacao['criadaEm'] : '' }}</p>
                                    @empty
                                        <p class="usuario-detalhes__nota">Nenhum registro.</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Convites de amizade</h3>
                            @forelse ($detalhes['convitesAmizade'] as $convite)
                                <p class="usuario-detalhes__valor">{{ $convite['direcao'] }} · {{ $convite['outraPessoa'] }} · {{ $convite['situacao'] }} · {{ $convite['criadoEm'] ?? 'Sem data' }}</p>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhum convite de amizade enviado.</p>
                            @endforelse
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Convites de mesa</h3>
                            @forelse ($detalhes['convitesMesa'] as $convite)
                                <p class="usuario-detalhes__valor">{{ $convite['direcao'] }} · {{ $convite['outraPessoa'] }} · {{ $convite['mesa'] }} · {{ $convite['situacao'] }} · {{ $convite['criadoEm'] ?? 'Sem data' }}</p>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhum convite de mesa registrado.</p>
                            @endforelse
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Mesas ({{ count($detalhes['mesas']) }})</h3>
                            @forelse ($detalhes['mesas'] as $mesa)
                                <div class="usuario-detalhes__linha">
                                    <strong class="usuario-detalhes__valor">{{ $mesa['nome'] }}</strong>
                                    <span class="usuario-detalhes__nota">{{ $mesa['situacao'] }} · {{ $mesa['participacao'] }}</span>
                                    <span class="usuario-detalhes__nota">Criada: {{ $mesa['criadaEm'] ?? 'Sem data' }} · Atualizada: {{ $mesa['atualizadaEm'] ?? '—' }}</span>
                                </div>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhuma mesa encontrada.</p>
                            @endforelse
                            <p class="usuario-detalhes__nota">O estado serializado da mesa não é aberto nesta consulta.</p>
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Partidas em mesa</h3>
                            <p class="usuario-detalhes__valor">{{ $detalhes['partidasEmMesa']['total'] }} partidas registradas em eventos.</p>
                            <p class="usuario-detalhes__nota">Última atividade em partida: {{ $detalhes['partidasEmMesa']['ultimaEm'] ?? 'Sem registro' }}</p>
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Jogos solo</h3>
                            @foreach ($detalhes['jogosSolo'] as $jogo)
                                <div class="usuario-detalhes__linha">
                                    <strong class="usuario-detalhes__valor">{{ $jogo['nome'] }} · {{ $jogo['total'] }} tentativas</strong>
                                    @forelse ($jogo['recentes'] as $tentativa)
                                        <span class="usuario-detalhes__nota">{{ $tentativa['iniciadaEm'] }} · {{ $tentativa['resultado'] }}{{ $tentativa['recorde'] ? ' · recorde' : '' }}{{ $tentativa['concluidaEm'] === null ? ' · não concluída' : '' }}</span>
                                    @empty
                                        <span class="usuario-detalhes__nota">Sem tentativas.</span>
                                    @endforelse
                                </div>
                            @endforeach
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Medalhas ({{ count($detalhes['medalhas']) }})</h3>
                            @forelse ($detalhes['medalhas'] as $medalha)
                                <p class="usuario-detalhes__valor">{{ $medalha['jogo'] }} · {{ $medalha['colocacao'] }}º lugar{{ $medalha['empate'] ? ' (empate)' : '' }} · {{ $medalha['mesa'] }} · {{ $medalha['data'] ?? 'Sem data' }}</p>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhuma medalha registrada.</p>
                            @endforelse
                        </section>

                        <section class="usuario-detalhes__secao">
                            <h3>Troféus ({{ count($detalhes['trofeus']) }})</h3>
                            @forelse ($detalhes['trofeus'] as $trofeu)
                                <p class="usuario-detalhes__valor">{{ $trofeu['tipo'] === 'empate' ? 'Troféu de empate' : 'Troféu de vitória' }} · {{ $trofeu['mesa'] }} · {{ $trofeu['data'] ?? 'Sem data' }}</p>
                            @empty
                                <p class="usuario-detalhes__nota">Nenhum troféu registrado.</p>
                            @endforelse
                        </section>

                        <p class="usuario-detalhes__nota">Consulta administrativa somente leitura. Senhas, tokens, IPs, portas e respostas brutas de partidas não são exibidos.</p>
                    </div>
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
