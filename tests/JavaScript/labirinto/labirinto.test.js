import { describe, it, expect, vi } from 'vitest';
vi.mock('phaser', () => ({ default: { Scene: class { constructor() {} } } }));
import { inicio, chegada, chegou, colide, linhas, tamanhoCelula, velocidade } from '../../../resources/js/jogos/labirinto/mapa.js';
import { posicaoValida } from '../../../resources/js/jogos/labirinto/cena.js';
import { mover } from '../../../resources/js/jogos/labirinto/movimento.js';
import { Cronometro, formatarTempo } from '../../../resources/js/jogos/labirinto/cronometro.js';
import { direcaoDoJoystick } from '../../../resources/js/jogos/labirinto/controles.js';

describe('Labirinto', () => {
    it('restaura somente posições finitas e livres de parede para o raio da bolinha', () => {
        const mapa = { colide };
        expect(posicaoValida(mapa, inicio)).toBe(true);
        expect(posicaoValida(mapa, { x: NaN, y: inicio.y })).toBe(false);
        expect(posicaoValida(mapa, { x: 39, y: 60 })).toBe(false);
        expect(posicaoValida(mapa, null)).toBe(false);
    });

    it('move na velocidade definida e normaliza diagonais', () => {
        const posicao = mover(inicio, { x: 1, y: 0 }, 50);
        expect(posicao.x - inicio.x).toBeCloseTo(velocidade * 0.05);
        const diagonal = mover(inicio, { x: 1, y: 1 }, 50);
        expect(Math.hypot(diagonal.x - inicio.x, diagonal.y - inicio.y)).toBeCloseTo(6);
        expect(mover(inicio, { x: 0, y: 0 }, 50)).toEqual(inicio);
    });

    it('impede atravessar paredes mesmo com quadros atrasados', () => {
        let posicao = { ...inicio };
        for (let passo = 0; passo < 100; passo++) posicao = mover(posicao, { x: -1, y: 0 }, 10000);
        expect(posicao.x).toBeGreaterThanOrEqual(50);
        expect(colide(posicao)).toBe(false);
        expect(colide({ x: 0, y: 0 })).toBe(true);
        expect(colide({ x: -10, y: -10 })).toBe(true);
    });

    it('reconhece apenas a chegada e permite percorrer o mapa inteiro', () => {
        expect(chegou(inicio)).toBe(false);
        expect(chegou(chegada)).toBe(true);
        const fila = [[1, 1, []]];
        const vistos = new Set(['1,1']);
        let caminho;
        while (fila.length) {
            const [coluna, linha, passos] = fila.shift();
            if (linhas[linha][coluna] === 'C') { caminho = passos; break; }
            for (const [x, y] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
                const chave = `${coluna + x},${linha + y}`;
                if (linhas[linha + y]?.[coluna + x] && linhas[linha + y][coluna + x] !== '#' && !vistos.has(chave)) {
                    vistos.add(chave);
                    fila.push([coluna + x, linha + y, [...passos, { x, y }]]);
                }
            }
        }
        expect(caminho).toBeDefined();
        let posicao = { ...inicio };
        for (const direcao of caminho) {
            // Vinte avanços de dois pixels equivalem a uma célula.
            for (let passo = 0; passo < 20; passo++) posicao = mover(posicao, direcao, tamanhoCelula / 20 / velocidade * 1000);
            expect(colide(posicao)).toBe(false);
        }
        expect(chegou(posicao)).toBe(true);
        expect((Math.hypot(chegada.x - inicio.x, chegada.y - inicio.y) - 12) / velocidade * 1000).toBeGreaterThan(4000);
    });

    it('alavanca tem zona morta e velocidade proporcional, limitada mesmo fora da base', () => {
        expect(direcaoDoJoystick(0, 0)).toEqual({ x: 0, y: 0 });
        expect(direcaoDoJoystick(3, 4)).toEqual({ x: 0, y: 0 });
        expect(direcaoDoJoystick(6, 0)).toEqual({ x: 0, y: 0 });
        expect(direcaoDoJoystick(27, 0)).toEqual({ x: 0.5, y: 0 });
        expect(direcaoDoJoystick(500, 0)).toEqual({ x: 1, y: 0 });
        expect(direcaoDoJoystick(0, -48)).toEqual({ x: 0, y: -1 });
        const diagonal = direcaoDoJoystick(-48, 48);
        expect(diagonal.x).toBeLessThan(0);
        expect(diagonal.y).toBeGreaterThan(0);
        expect(Math.hypot(diagonal.x, diagonal.y)).toBeCloseTo(1);
        expect(mover(inicio, direcaoDoJoystick(27, 0), 50).x).toBeCloseTo(63);
        expect(mover(inicio, direcaoDoJoystick(500, 0), 50).x).toBeCloseTo(66);
    });

    it('cronometra a partir do movimento, conta tempo fora da aba e congela na chegada', () => {
        const cronometro = new Cronometro();
        expect(cronometro.tempo(1000)).toBe(0);
        cronometro.iniciar(1000);
        cronometro.iniciar(2000);
        expect(cronometro.tempo(11000)).toBe(10000);
        expect(cronometro.concluir(12345)).toBe(11345);
        expect(cronometro.concluir(20000)).toBe(11345);
        expect(cronometro.tempo(30000)).toBe(11345);
        expect(formatarTempo(11345)).toBe('11,345 s');
    });

    it('permite alinhar o início do cronômetro a uma liberação futura', () => {
        const cronometro = new Cronometro();
        cronometro.iniciar(2000);
        expect(cronometro.tempo(1500)).toBe(0);
        expect(cronometro.tempo(2500)).toBe(500);
    });
});
