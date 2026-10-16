<x-filament-panels::page>
    <style>
        .editor-labirinto { display: grid; gap: 24px; }
        .editor-labirinto [x-cloak] { display: none !important; }
        .editor-labirinto .barra {
            display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
        }
        .editor-labirinto .navegacao {
            display: flex; flex-wrap: wrap; gap: 8px;
            padding-bottom: 16px; border-bottom: 1px solid #706a6266;
        }
        .editor-labirinto .navegacao button {
            padding: 10px 16px; border: 1px solid #706a6266;
            border-radius: 8px; font-weight: 600; cursor: pointer;
        }
        .editor-labirinto .navegacao button[aria-pressed="true"] {
            background: #ff684d; border-color: #ff684d; color: #20201f;
        }
        .editor-labirinto .navegacao button:focus-visible {
            outline: 2px solid #ff684d; outline-offset: 3px;
        }
        .editor-labirinto .secao { display: grid; gap: 20px; }
        .editor-labirinto .edicao-com-importacao { display: grid; gap: 24px; align-items: start; }
        .editor-labirinto .area-desenho, .editor-labirinto .importacao { display: grid; gap: 20px; min-width: 0; }
        .editor-labirinto .importacao { border: 1px solid #706a6266; border-radius: 12px; padding: 20px; }
        .editor-labirinto textarea { width: 100%; min-width: 0; box-sizing: border-box; resize: vertical; background: #fff; color: #20201f; border: 1px solid #706a62; border-radius: 8px; padding: 12px; font-family: monospace; }
        @media (min-width: 1000px) {
            .editor-labirinto .edicao-com-importacao { grid-template-columns: minmax(0, 520px) minmax(280px, 1fr); }
        }
        .editor-labirinto h2 { font-size: 1.125rem; font-weight: 700; }
        .editor-labirinto .campo { display: grid; gap: 6px; }
        .editor-labirinto input, .editor-labirinto select {
            background: #fff; color: #20201f; border: 1px solid #706a62;
            border-radius: 8px; padding: 10px; max-width: 100%;
        }
        .editor-labirinto .campo-nome { width: 100%; max-width: 520px; }
        .editor-labirinto .tabuleiro { overflow-x: auto; }
        .editor-labirinto .grade {
            display: grid; grid-template-columns: repeat(13, minmax(0, 1fr));
            width: 100%; max-width: 520px;
        }
        .editor-labirinto .celula {
            aspect-ratio: 1; min-width: 0; border: 1px solid #706a6233;
            background: #f7f3ec; color: #20201f; font-weight: 800;
        }
        .editor-labirinto .parede { background: #20201f; color: #f7f3ec; }
        .editor-labirinto .marco { background: #ff684d; color: #20201f; }
        .editor-labirinto .celula:focus-visible {
            outline: 3px solid #ff684d; outline-offset: -3px;
        }
        .editor-labirinto .celula:disabled { cursor: default; }
        .editor-labirinto .catalogo {
            display: grid; gap: 16px;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 240px), 1fr));
        }
        .editor-labirinto .cartao {
            display: grid; gap: 12px; align-content: start; padding: 16px;
            border: 1px solid #706a6266; border-radius: 12px;
        }
        .editor-labirinto .cartao h3 { font-weight: 700; overflow-wrap: anywhere; }
        .editor-labirinto .erro {
            color: #bd392b; background: #fff; padding: 12px; border-radius: 8px;
        }
        .editor-labirinto summary { cursor: pointer; padding: 12px 0; }
        .editor-labirinto .versao { max-width: 520px; }
    </style>

    <div class="editor-labirinto" x-data="{ area: 'mapas' }">
        <nav class="navegacao" aria-label="Áreas dos labirintos">
            <button type="button" x-on:click="area = 'mapas'"
                x-bind:aria-pressed="area === 'mapas'" aria-controls="labirinto-mapas">
                Mapas cadastrados
            </button>
            <button type="button" wire:loading.attr="disabled"
                x-on:click="if (($wire.mapaId === null && $wire.nome === '' && $wire.historico.length === 0 && $wire.jsonImportacao === '') || window.confirm('Descartar alterações não salvas e criar um novo mapa?')) { $wire.novo().then(() => area = 'editor') }"
                x-bind:aria-pressed="area === 'editor' && $wire.mapaId === null" aria-controls="labirinto-editor">
                Novo mapa
            </button>
        </nav>

        <section id="labirinto-mapas" class="secao" x-show="area === 'mapas'"
            aria-label="Mapas cadastrados">
            <h2>Mapas cadastrados</h2>
            <div class="catalogo">
                @forelse ($mapas as $mapa)
                    <article class="cartao" wire:key="mapa-{{ $mapa['id'] }}">
                        <h3>{{ $mapa['nome'] }}</h3>
                        <p>Prévia do rascunho</p>
                        <x-labirinto-miniatura :linhas="$mapa['linhas']" :nome="'Rascunho de '.$mapa['nome']" />
                        <p>{{ $mapa['versao_publicada'] === null ? 'Sem versão publicada' : 'Publicação: '.($mapa['ativo'] ? 'ativa' : 'inativa').' · versão '.$mapa['versao_publicada'] }}</p>
                        <p>Uso em rodadas: {{ $mapa['usos_em_rodadas'] === null ? 'Ainda não registrado' : $mapa['usos_em_rodadas'] }}</p>
                        <x-filament::button color="gray" wire:loading.attr="disabled"
                            x-on:click="if (window.confirm('Abrir este mapa e descartar alterações não salvas?')) { $wire.abrir({{ $mapa['id'] }}).then(() => area = 'editor') }">
                            Editar
                        </x-filament::button>
                    </article>
                @empty
                    <p>Nenhum mapa cadastrado. Use Novo mapa para criar o primeiro.</p>
                @endforelse
            </div>
        </section>

        <section id="labirinto-editor" class="secao" x-show="area === 'editor'"
            x-cloak aria-label="Editor do mapa">
            <div class="barra">
                <h2>{{ $mapaId === null ? 'Novo mapa' : 'Editar mapa' }}</h2>
            </div>

            <div class="barra">
                <span>{{ $mapaId === null ? 'Novo rascunho' : 'Mapa #'.$mapaId }}</span>
                <span>{{ $versaoPublicada === null ? 'Ainda não publicado' : 'Versão publicada: '.$versaoPublicada }}</span>
                @if ($versaoPublicada !== null)
                    <span>{{ $ativo ? 'Ativo' : 'Inativo' }}</span>
                @endif
            </div>

            <div class="edicao-com-importacao">
            <div class="area-desenho">
            <label class="campo campo-nome">
                Nome do desafio
                <input type="text" wire:model="nome" maxlength="80" autocomplete="off">
            </label>

            <div class="barra">
                <label class="campo">
                    Ferramenta
                    <select wire:model="ferramenta">
                        <option value="#">Parede</option>
                        <option value=".">Caminho</option>
                        <option value="I">Início</option>
                        <option value="C">Chegada</option>
                    </select>
                </label>
                <x-filament::button color="gray" wire:click="desfazer" :disabled="$historico === []">
                    Desfazer
                </x-filament::button>
                <x-filament::button color="gray" wire:click="duplicar">
                    Duplicar rascunho
                </x-filament::button>
            </div>

            @error('mapa')
                <p class="erro" role="alert">{{ $message }}</p>
            @enderror

            <div class="tabuleiro">
                <div class="grade" role="group" aria-label="Editor do labirinto, 13 linhas e 13 colunas">
                    @foreach ($linhas as $y => $linha)
                        @foreach (str_split($linha) as $x => $celula)
                            @php($rotulo = match ($celula) { '#' => 'parede', '.' => 'caminho', 'I' => 'início', 'C' => 'chegada' })
                            <button type="button"
                                wire:key="celula-{{ $y }}-{{ $x }}"
                                wire:click="pintar({{ $y }}, {{ $x }})"
                                wire:loading.attr="disabled"
                                @disabled($x === 0 || $y === 0 || $x === 12 || $y === 12)
                                @class(['celula', 'parede' => $celula === '#', 'marco' => in_array($celula, ['I', 'C'], true)])
                                aria-label="Linha {{ $y + 1 }}, coluna {{ $x + 1 }}: {{ $rotulo }}"
                            >{{ in_array($celula, ['I', 'C'], true) ? $celula : '' }}</button>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <form action="{{ route('painel.labirinto.previa') }}" method="POST" target="_blank"
                x-on:submit.prevent="if (await $wire.validarTeste()) { $el.submit() }">
                @csrf
                <input type="hidden" name="mapa" x-bind:value="JSON.stringify({ formato: 1, nome: $wire.nome.trim() || 'Prévia sem nome', linhas: $wire.linhas })">
                <x-filament::button type="submit" color="gray" wire:loading.attr="disabled">Testar rascunho em nova aba</x-filament::button>
                <p>Jogue antes de publicar. O teste não salva tempos nem altera estatísticas.</p>
            </form>
            <div class="barra">
                <x-filament::button color="gray" wire:click="salvar" wire:loading.attr="disabled">
                    Salvar rascunho
                </x-filament::button>
                <x-filament::button wire:click="salvar(true)" wire:loading.attr="disabled">
                    Publicar versão e ativar
                </x-filament::button>
                @if ($versaoPublicada !== null)
                    <x-filament::button color="gray" wire:click="alternarAtivo" wire:loading.attr="disabled">
                        {{ $ativo ? 'Desativar mapa' : 'Ativar mapa' }}
                    </x-filament::button>
                @endif
            </div>
            </div>
            <aside class="importacao" aria-label="Importar mapa (JSON)">
                <h2>Importar mapa (JSON)</h2>
                <label class="campo">
                    JSON do mapa
                    <textarea wire:model="jsonImportacao" rows="16" maxlength="8192" spellcheck="false"
                        aria-describedby="importacao-ajuda" @error('jsonImportacao') aria-invalid="true" @enderror></textarea>
                </label>
                <p id="importacao-ajuda">Cole o JSON para conferir e editar o desenho. Carregar cria um novo rascunho no editor; não salva nem publica.</p>
                @error('jsonImportacao')
                    <p class="erro" role="alert">{{ $message }}</p>
                @enderror
                <x-filament::button color="gray" wire:click="importar" wire:loading.attr="disabled"
                    wire:confirm="Carregar o JSON como novo mapa e substituir as alterações não salvas do editor?">
                    Carregar no editor
                </x-filament::button>
                @if ($mapaRepetidoId !== null)
                    <x-filament::button color="gray" wire:click="abrir({{ $mapaRepetidoId }})"
                        wire:confirm="Abrir o mapa existente e descartar alterações não salvas?">
                        Abrir mapa existente
                    </x-filament::button>
                @endif
            </aside>
            </div>
        <details>
            <summary>Histórico de versões</summary>
            @forelse ($versoes as $versao)
                <details class="versao" wire:key="versao-{{ $versao->id }}">
                    <summary>Versão {{ $versao->numero }} · {{ $versao->nome }} · {{ $versao->publicada_em }} UTC</summary>
                    @php($desenho = json_decode($versao->desenho, true, 512, JSON_THROW_ON_ERROR))
                    <x-labirinto-miniatura :linhas="$desenho['linhas']" :nome="'Versão '.$versao->numero.' de '.$versao->nome" />
                </details>
            @empty
                <p>{{ $mapaId === null ? 'Abra um mapa cadastrado para consultar suas versões.' : 'Este mapa ainda não tem versões publicadas.' }}</p>
            @endforelse
        </details>
        </section>
    </div>
</x-filament-panels::page>
