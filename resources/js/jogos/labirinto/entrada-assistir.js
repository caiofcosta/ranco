import Phaser from 'phaser';
import '../../echo.js';
import { CenaLabirinto } from './cena.js';
import { criarMapa } from './mapa.js';
import { aceitarAmostra, interpolarAmostras } from './transmissao.js';

let encerrar;
function iniciar() {
    const painel = document.querySelector('[data-labirinto-assistir]');
    if (!painel || encerrar) return;
    const mapa = criarMapa(JSON.parse(painel.querySelector('[data-definicao]').textContent) ?? {});
    const contexto = JSON.parse(painel.dataset.contexto);
    const aviso = painel.querySelector('[data-aviso]');
    const tabuleiro = painel.querySelector('[data-tabuleiro]');
    tabuleiro.replaceChildren();
    const nome = `mesa.${contexto.mesa}`;
    const canal = window.Echo.private(nome);
    let anterior = null;
    let atual = null;
    const reler = () => {
        // O painel revalida o contexto sem remontar o canvas em atualizações do placar.
        if (!document.querySelector('[data-tela-do-jogo]')) window.location.reload();
    };
    canal.listenForWhisper('labirinto.amostra', (dados) => {
        if (!aceitarAmostra(dados, contexto, atual, mapa)) return;
        anterior = atual;
        atual = { ...dados, recebidaEm: performance.now() };
    });
    // Só o sinal oficial reconsulta autorização/vez; um whisper nunca encerra uma tentativa.
    canal.listen('.mesa.mudou', reler);
    canal.error(() => { aviso.textContent = 'Transmissão indisponível. Volte para a mesa.'; });
    const conexao = window.Echo.connector.pusher.connection;
    let conectou = conexao.state === 'connected';
    const reconectou = () => { if (conectou) reler(); conectou = true; };
    conexao.bind('connected', reconectou);
    const jogo = new Phaser.Game({
        type: Phaser.AUTO, parent: tabuleiro, width: mapa.largura, height: mapa.altura,
        backgroundColor: '#EAE2D5', audio: { noAudio: true },
        input: { keyboard: false, mouse: false, touch: false, gamepad: false },
        scale: { mode: Phaser.Scale.FIT, autoCenter: Phaser.Scale.CENTER_BOTH },
        scene: new CenaLabirinto(null, painel.querySelector('[data-tempo]'), mapa, {
            leitura: () => {
                const agora = performance.now();
                if (atual) aviso.textContent = agora - atual.recebidaEm > 3000
                    ? 'Transmissão interrompida. Aguardando reconexão…'
                    : atual.tipo === 'chegada' ? 'Chegou à saída. Aguardando confirmação…' : 'Ao vivo';
                return interpolarAmostras(anterior, atual, agora);
            },
        }),
    });
    encerrar = () => {
        conexao.unbind('connected', reconectou);
        window.Echo.leave(nome);
        jogo.destroy(true);
        encerrar = null;
    };
}
// Depois do ciclo da mesa liberar sua inscrição na navegação Livewire.
const agendar = () => setTimeout(iniciar, 0);
document.addEventListener('livewire:navigating', () => encerrar?.());
window.addEventListener('pagehide', () => encerrar?.());
document.addEventListener('livewire:navigated', agendar);
agendar();

// O histórico nativo pode restaurar uma página cuja inscrição já foi encerrada.
window.addEventListener('pageshow', (evento) => {
    if (evento.persisted) window.location.reload();
});
