import Phaser from 'phaser';
import { mapaPadrao } from './mapa.js';
import { mover } from './movimento.js';
import { Cronometro, formatarTempo } from './cronometro.js';
import { criarJoystick } from './joystick.js';

export function posicaoValida(mapa, posicao) {
    return posicao !== null && typeof posicao === 'object'
        && Number.isFinite(posicao.x) && Number.isFinite(posicao.y)
        && !mapa.colide(posicao);
}

export class CenaLabirinto extends Phaser.Scene {
    constructor(concluir, mostrador, mapa = mapaPadrao, opcoes = {}) {
        super('labirinto');
        this.opcoes = opcoes;
        this.concluir = concluir;
        this.mostrador = mostrador;
        this.cronometro = new Cronometro();
        this.mapa = mapa;
        this.posicao = { ...mapa.inicio };
    }

    create() {
        const { linhas, tamanhoCelula, inicio, chegada, raioBolinha } = this.mapa;
        const posicaoInicial = posicaoValida(this.mapa, this.opcoes.posicaoInicial)
            ? this.opcoes.posicaoInicial : inicio;
        this.posicao = { ...posicaoInicial };
        this.cronometro = new Cronometro();
        const offsetInicial = this.opcoes.offsetInicial ?? 0;
        if (this.opcoes.iniciarCronometro || offsetInicial > 0) {
            this.cronometro.iniciar(performance.now() - offsetInicial);
        }
        this.mostrador.textContent = formatarTempo(this.cronometro.tempo(performance.now()));
        const desenho = this.add.graphics();
        desenho.fillStyle(0x20201f);
        linhas.forEach((linha, indiceLinha) => [...linha].forEach((celula, indiceColuna) => {
            if (celula === '#') desenho.fillRect(indiceColuna * tamanhoCelula, indiceLinha * tamanhoCelula, tamanhoCelula, tamanhoCelula);
        }));
        this.add.circle(chegada.x, chegada.y, 15, 0x20201f);
        this.add.text(chegada.x, chegada.y, '✓', { fontSize: '22px', color: '#F7F3EC' }).setOrigin(0.5);
        this.bolinha = this.add.circle(this.posicao.x, this.posicao.y, raioBolinha, 0xff684d).setStrokeStyle(2, 0x20201f);
        if (this.opcoes.leitura) {
            this.bolinha.setVisible(false);
            this.mostrador.textContent = '—';
            this.game.canvas.setAttribute('aria-label', 'Transmissão ao vivo do Labirinto, somente leitura.');
            return;
        }
        this.input.on('pointerdown', () => this.game.canvas.focus());
        this.joystick = criarJoystick(this.game.canvas.parentElement);
        const joystick = this.joystick;
        const limparJoystick = () => {
            joystick.destruir();
            this.events.off('shutdown', limparJoystick);
            this.events.off('destroy', limparJoystick);
        };
        this.events.once('shutdown', limparJoystick);
        this.events.once('destroy', limparJoystick);
        this.game.canvas.tabIndex = 0;
        this.game.canvas.setAttribute('aria-label', 'Labirinto. Leve a bolinha até a chegada usando a alavanca abaixo do tabuleiro. Arraste para mover e solte para parar.');
        this.game.canvas.style.touchAction = 'none';
    }

    update(tempo, intervalo) {
        if (this.opcoes.leitura) {
            const amostra = this.opcoes.leitura();
            if (amostra) {
                this.bolinha.setVisible(true).setPosition(amostra.x, amostra.y);
                this.mostrador.textContent = formatarTempo(amostra.tempo);
            }
            return;
        }
        if (this.cronometro.fim !== null || this.opcoes.bloqueada?.()) return;
        const agora = performance.now();
        const direcao = this.joystick.direcao;
        const proxima = mover(this.posicao, direcao, intervalo, this.mapa);
        const mudou = proxima.x !== this.posicao.x || proxima.y !== this.posicao.y;
        if (mudou) this.cronometro.iniciar(agora);
        this.posicao = proxima;
        this.bolinha.setPosition(proxima.x, proxima.y);
        this.mostrador.textContent = formatarTempo(this.cronometro.tempo(agora));
        const chegou = mudou && this.mapa.chegou(proxima);
        const decorrido = chegou ? this.cronometro.concluir(agora) : this.cronometro.tempo(agora);
        if (mudou && this.opcoes.persistir === true) this.opcoes.aoMover?.(proxima, decorrido);
        this.opcoes.observar?.(proxima, decorrido, chegou);
        if (chegou) this.concluir(decorrido);
    }
}
