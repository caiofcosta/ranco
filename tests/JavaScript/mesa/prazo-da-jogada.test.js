import { describe, expect, it } from 'vitest';
import { criarRelogioDoPrazo } from '../../../resources/js/mesa/prazo-da-jogada.js';

describe('prazo e preparação da jogada', () => {
    it('trava por três segundos e libera com o prazo completo, usando referência do servidor', () => {
        let tempo = 500;
        const consultar = criarRelogioDoPrazo({ agoraEm: '2026-10-05T12:00:00Z', liberaEm: '2026-10-05T12:00:03Z', expiraEm: '2026-10-05T12:02:03Z', tempo: () => tempo });
        expect(consultar()).toMatchObject({ preparacao: 3, podeMover: false, anel: 100 });
        tempo += 1000;
        expect(consultar()).toMatchObject({ preparacao: 2, podeMover: false });
        tempo += 2000;
        expect(consultar()).toMatchObject({ preparacao: 0, podeMover: true, segundos: 120, avisoFinal: false });
        tempo += 90000;
        expect(consultar()).toMatchObject({ segundos: 30, expirou: false, avisoFinal: true });
        tempo += 30000;
        expect(consultar()).toMatchObject({ segundos: 0, expirou: true, podeMover: false });
    });
    it('recarregar preserva o prazo e não reinicia uma preparação terminada', () => {
        const consultar = criarRelogioDoPrazo({ agoraEm: '2026-10-05T12:01:40Z', liberaEm: '2026-10-05T12:00:03Z', expiraEm: '2026-10-05T12:02:03Z', tempo: () => 0 });
        expect(consultar()).toMatchObject({ preparacao: 0, segundos: 23, podeMover: true });
    });
    it('sem prazo de uma tentativa antiga não inventa expiração', () => {
        expect(criarRelogioDoPrazo({ agoraEm: '2026-10-05T12:00:00Z', expiraEm: '', liberaEm: '', tempo: () => 0 })()).toMatchObject({ segundos: null, podeMover: true, preparacao: 0 });
    });
});
