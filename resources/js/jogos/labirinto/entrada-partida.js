import Phaser from 'phaser';
import { consultarPrazoDaTela } from '../../mesa/prazo-da-jogada.js';
import { CenaLabirinto, posicaoValida } from './cena.js';
import { criarMapa } from './mapa.js';
import { publicarJogada } from './publicar-jogada.js';
import { enviarResultadoDaPartida } from './envio-da-partida.js';

let jogo;
let cancelar;
let transmissao;
let salvarPosicaoAtual;
// O registro de navegação do navegador é o da abertura do documento. Numa volta à tela pelo
// wire:navigate ele continua o mesmo, e somar o carregamento contaria de novo o tempo fora.
let primeiraMontagem = true;

function iniciar() {
    const painel = document.querySelector('[data-labirinto-partida]');
    if (!painel || jogo) return;
    const mapa = criarMapa(JSON.parse(painel.querySelector('[data-definicao]').textContent) ?? {});
    cancelar = new AbortController();
    const sinal = cancelar.signal;
    const aviso = painel.querySelector('[data-aviso]');
    const repetir = painel.querySelector('[data-reenviar]');
    const tabuleiro = painel.querySelector('[data-tabuleiro]');
    // O histórico do Livewire restaura HTML que pode conter um canvas antigo.
    tabuleiro.replaceChildren();
    painel.querySelector('[data-tempo]').textContent = '0,000 s';
    aviso.textContent = '';
    aviso.classList.remove('text-danger');
    aviso.setAttribute('role', 'status');
    repetir.hidden = true;
    const antesDeIniciar = painel.dataset.antesDeIniciar === '1';
    const dadosPrazo = document.querySelector('[data-prazo-da-jogada]')?.dataset;
    let offsetInicial = 0;
    if (!antesDeIniciar && dadosPrazo?.agora && dadosPrazo?.libera) {
        const agora = Date.parse(dadosPrazo.agora);
        const libera = Date.parse(dadosPrazo.libera);
        if (Number.isFinite(agora) && Number.isFinite(libera)) {
            const navegacao = performance.getEntriesByType('navigation')[0];
            const urlNavegacao = navegacao ? new URL(navegacao.name, window.location.href) : null;
            const mesmaNavegacao = urlNavegacao
                && urlNavegacao.pathname === window.location.pathname
                && urlNavegacao.search === window.location.search;
            const carregamento = primeiraMontagem && mesmaNavegacao && Number.isFinite(navegacao.responseStart) && navegacao.responseStart > 0
                ? Math.max(0, performance.now() - navegacao.responseStart) : 0;
            offsetInicial = agora - libera + carregamento;
        }
    }
    primeiraMontagem = false;
    const prefixo = !antesDeIniciar && painel.dataset.participante && painel.dataset.mapaVersao
        ? `ranco:labirinto:partida:${encodeURIComponent(painel.dataset.mesa)}:${encodeURIComponent(painel.dataset.participante)}:`
        : null;
    const chave = prefixo
        ? `${prefixo}${[
            painel.dataset.partida,
            painel.dataset.rodada,
            painel.dataset.desempate,
            painel.dataset.mapaVersao,
        ].map((parte) => encodeURIComponent(parte)).join(':')}`
        : null;
    function removerPosicao() {
        if (!chave) return;
        try {
            window.sessionStorage.removeItem(chave);
        } catch {
            // Armazenamento indisponível não pode interromper a jogada.
        }
    }
    function lerPosicao() {
        if (!chave) return null;
        try {
            const salvo = window.sessionStorage.getItem(chave);
            if (salvo === null) return null;
            const posicao = JSON.parse(salvo);
            // Na chegada, o primeiro toque concluiria a vez de novo: a volta recomeça da largada.
            if (posicaoValida(mapa, posicao) && !mapa.chegou(posicao)) return { x: posicao.x, y: posicao.y };
            removerPosicao();
        } catch {
            removerPosicao();
        }
        return null;
    }
    function descartarTentativasAnteriores() {
        if (!chave) return;
        try {
            const anteriores = [];
            for (let indice = 0; indice < window.sessionStorage.length; indice++) {
                const existente = window.sessionStorage.key(indice);
                if (existente?.startsWith(prefixo) && existente !== chave) anteriores.push(existente);
            }
            anteriores.forEach((existente) => window.sessionStorage.removeItem(existente));
        } catch {
            // Uma falha de limpeza nunca impede a retomada atual.
        }
    }
    const posicaoInicial = lerPosicao();
    descartarTentativasAnteriores();
    let posicaoAtual = posicaoInicial ?? mapa.inicio;
    let ultimoSalvamento = 0;
    let resultadoConfirmado = false;
    function salvarPosicao(forcar = false) {
        if (!chave || resultadoConfirmado || !posicaoValida(mapa, posicaoAtual)) return;
        const agora = Date.now();
        if (!forcar && agora - ultimoSalvamento < 100) return;
        try {
            window.sessionStorage.setItem(chave, JSON.stringify(posicaoAtual));
            ultimoSalvamento = agora;
        } catch {
            // Armazenamento indisponível não pode interromper a jogada.
        }
    }
    salvarPosicaoAtual = () => salvarPosicao(true);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') salvarPosicao(true);
    }, { signal: sinal });
    if (!antesDeIniciar && painel.dataset.transmissao) {
        import('../../echo.js').then(() => {
            if (!sinal.aborted) transmissao = publicarJogada(window.Echo, JSON.parse(painel.dataset.transmissao), mapa);
        }).catch(() => {});
    }
    let tempoFinal;
    let enviando = false;

    async function enviar() {
        if (enviando) return;
        enviando = true;
        repetir.hidden = true;
        aviso.classList.remove('text-danger');
        aviso.setAttribute('role', 'status');
        aviso.textContent = 'Labirinto concluído. Enviando seu tempo…';
        try {
            const retorno = await enviarResultadoDaPartida({
                destino: painel.dataset.destino,
                csrf: painel.dataset.csrf,
                tempo: tempoFinal,
                jogada: painel.dataset.jogada,
                partida: Number(painel.dataset.partida),
                rodada: Number(painel.dataset.rodada),
                desempate: Number(painel.dataset.desempate),
                sinal,
            });
            if (sinal.aborted) return;
            if (retorno.destino) {
                resultadoConfirmado = true;
                removerPosicao();
                window.location.assign(retorno.destino);
                return;
            }
            aviso.classList.add('text-danger');
            aviso.setAttribute('role', 'alert');
            aviso.textContent = retorno.mensagem;
            repetir.hidden = !retorno.podeReenviar;
        } catch (erro) {
            if (!sinal.aborted) {
                aviso.classList.add('text-danger');
                aviso.setAttribute('role', 'alert');
                aviso.textContent = 'Não foi possível confirmar seu tempo. Tente enviar novamente.';
                repetir.hidden = false;
            }
        } finally {
            enviando = false;
        }
    }

    repetir.addEventListener('click', enviar, { signal: sinal });
    jogo = new Phaser.Game({
        type: Phaser.AUTO,
        parent: tabuleiro,
        width: mapa.largura,
        height: mapa.altura,
        backgroundColor: '#EAE2D5',
        audio: { noAudio: true },
        scale: { mode: Phaser.Scale.FIT, autoCenter: Phaser.Scale.CENTER_BOTH },
        scene: new CenaLabirinto((tempo) => { tempoFinal = tempo; enviar(); }, painel.querySelector('[data-tempo]'), mapa, {
            bloqueada: () => antesDeIniciar || !consultarPrazoDaTela().podeMover,
            observar: (...amostra) => transmissao?.observar(...amostra),
            offsetInicial,
            iniciarCronometro: !antesDeIniciar && Boolean(dadosPrazo?.libera),
            posicaoInicial,
            persistir: Boolean(chave),
            aoMover: (posicao) => {
                posicaoAtual = posicao;
                salvarPosicao();
            },
        }),
    });
}

function encerrar() {
    salvarPosicaoAtual?.();
    salvarPosicaoAtual = null;
    transmissao?.encerrar();
    transmissao = null;
    cancelar?.abort();
    jogo?.destroy(true);
    jogo = null;
}
document.addEventListener('livewire:navigating', encerrar);
window.addEventListener('pagehide', encerrar);
document.addEventListener('livewire:navigated', iniciar);
iniciar();

// O histórico nativo pode restaurar uma página cuja inscrição já foi encerrada.
window.addEventListener('pageshow', (evento) => {
    if (evento.persisted) window.location.reload();
});
