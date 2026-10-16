import Phaser from 'phaser';
import { CenaLabirinto } from './cena.js';
import { criarMapa } from './mapa.js';
import { enviarResultadoDoSolo } from './envio-do-solo.js';

let jogo;
let cancelar;

function iniciar() {
    const painel = document.querySelector('[data-labirinto]');
    if (!painel || jogo) return;
    cancelar = new AbortController();
    const sinal = cancelar.signal;
    const aviso = painel.querySelector('[data-aviso]');
    const repetir = painel.querySelector('[data-reenviar]');
    const tabuleiro = painel.querySelector('[data-tabuleiro]');
    // O histórico do Livewire pode restaurar o HTML com o canvas da tentativa anterior.
    tabuleiro.replaceChildren();
    painel.querySelector('[data-tempo]').textContent = '0,000 s';
    aviso.textContent = '';
    repetir.hidden = true;
    const mapa = criarMapa(JSON.parse(painel.querySelector('[data-definicao]').textContent));
    const previa = painel.dataset.previa === 'true';
    let tempoFinal;
    let enviando = false;
    async function enviar() {
        if (previa) {
            aviso.textContent = 'Teste concluído. Nenhum tempo foi salvo.';
            return;
        }
        if (enviando) return;
        enviando = true;
        repetir.hidden = true;
        aviso.textContent = 'Labirinto concluído. Salvando seu tempo…';
        try {
            const resultado = await enviarResultadoDoSolo({
                destino: painel.dataset.destino,
                csrf: painel.dataset.csrf,
                tempo: tempoFinal,
                tentativa: painel.dataset.tentativa,
                sinal,
            });
            if (resultado.destino) {
                window.location.assign(resultado.destino);
                return;
            }
            aviso.textContent = `Seu tempo não foi salvo. ${resultado.mensagem}`;
            repetir.hidden = !resultado.podeReenviar;
        } catch (erro) {
            if (!sinal.aborted) throw erro;
        } finally {
            enviando = false;
        }
    }
    repetir.addEventListener('click', enviar, { signal: sinal });
    painel.querySelector('[data-reiniciar]')?.addEventListener('click', () => {
        aviso.textContent = '';
        jogo.scene.getScene('labirinto').scene.restart();
    }, { signal: sinal });
    jogo = new Phaser.Game({
        type: Phaser.AUTO,
        parent: tabuleiro,
        width: mapa.largura,
        height: mapa.altura,
        backgroundColor: '#EAE2D5',
        audio: { noAudio: true },
        scale: { mode: Phaser.Scale.FIT, autoCenter: Phaser.Scale.CENTER_BOTH },
        scene: new CenaLabirinto((tempo) => { tempoFinal = tempo; enviar(); }, painel.querySelector('[data-tempo]'), mapa),
    });
}

document.addEventListener('livewire:navigating', () => {
    cancelar?.abort();
    jogo?.destroy(true);
    jogo = null;
});
document.addEventListener('livewire:navigated', iniciar);
iniciar();
