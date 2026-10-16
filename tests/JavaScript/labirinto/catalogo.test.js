import { describe, it, expect } from 'vitest';
import mapas from '../../../database/seeders/mapas-labirinto.json';
import { criarMapa, mapaPadrao } from '../../../resources/js/jogos/labirinto/mapa.js';
import { mover } from '../../../resources/js/jogos/labirinto/movimento.js';

describe('Catálogo jogável', () => {
    it.each(mapas)('$nome tem um caminho percorrível com a física do jogo', ({ linhas }) => {
        const mapa = criarMapa({ linhas });
        const fila = [[1, 1, []]];
        const vistos = new Set(['1,1']);
        let caminho;
        while (fila.length) {
            const [x, y, passos] = fila.shift();
            if (linhas[y][x] === 'C') { caminho = passos; break; }
            for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
                const chave = `${x + dx},${y + dy}`;
                if (linhas[y + dy]?.[x + dx] && linhas[y + dy][x + dx] !== '#' && !vistos.has(chave)) {
                    vistos.add(chave);
                    fila.push([x + dx, y + dy, [...passos, { x: dx, y: dy }]]);
                }
            }
        }
        expect(caminho).toBeDefined();
        let posicao = { ...mapa.inicio };
        for (const direcao of caminho) {
            for (let i = 0; i < 20; i++) {
                posicao = mover(posicao, direcao, 1000 / 60, mapa);
                expect(mapa.colide(posicao)).toBe(false);
            }
        }
        expect(mapa.chegou(posicao)).toBe(true);
    });

    it('isola mapas simultâneos e mantém o padrão das mesas', () => {
        const a = criarMapa({ linhas: mapas[0].linhas });
        const b = criarMapa({ linhas: mapas[1].linhas });
        expect(a.linhas).not.toEqual(b.linhas);
        expect(mapaPadrao.linhas[1]).toBe('#I....#.....#');
        expect(a.chegou(a.inicio)).toBe(false);
        expect(b.colide({ x: -1, y: -1 })).toBe(true);
    });
});
