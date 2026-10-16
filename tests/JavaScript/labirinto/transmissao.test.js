import { describe, it, expect } from 'vitest';
import { montarAmostra, validarAmostra, aceitarAmostra, interpolarAmostras } from '../../../resources/js/jogos/labirinto/transmissao.js';
const contexto = { mesa: 'mesa-1', partida: 1, rodada: 2, desempate: 0, participante: 'ana' };
const amostra = () => montarAmostra(contexto, { x: 60, y: 80 }, 1250, 'posicao', 123456);

describe('transmissão visual do labirinto', () => {
    it('monta somente o contrato público, sem comprovante ou dados extras', () => {
        expect(amostra()).toEqual({ ...contexto, versao: 1, tipo: 'posicao', enviadoEm: 123456, x: 60, y: 80, tempo: 1250 });
        expect(montarAmostra({ ...contexto, jogada: 'segredo' }, { x: 60, y: 80 }, 0, 'inicio', 1)).not.toHaveProperty('jogada');
        for (const tipo of ['inicio', 'posicao', 'chegada']) expect(validarAmostra({ ...amostra(), tipo }, contexto)).toBe(true);
    });
    it('ignora outra mesa, partida, rodada, desempate e participante', () => {
        for (const campo of Object.keys(contexto)) expect(validarAmostra({ ...amostra(), [campo]: 'outro' }, contexto)).toBe(false);
    });
    it('recusa formato inválido, extensões, números não finitos e coordenadas fora do mapa', () => {
        for (const valor of [null, [], 'texto', {}, { ...amostra(), extra: true }]) expect(validarAmostra(valor, contexto)).toBe(false);
        for (const [campo, valores] of Object.entries({ x: [NaN, Infinity, -1, 511, '60'], y: [NaN, -1, 511], tempo: [-1, 86400001, 0.1, '1'], enviadoEm: [-1, Infinity, 0.1], tipo: ['fim', null], versao: [2] })) {
            for (const valor of valores) expect(validarAmostra({ ...amostra(), [campo]: valor }, contexto)).toBe(false);
        }
    });
    it('rejeita mensagens antigas e duplicadas', () => {
        expect(aceitarAmostra(amostra(), contexto, null)).toBe(true);
        expect(aceitarAmostra(amostra(), contexto, amostra())).toBe(false);
        expect(aceitarAmostra({ ...amostra(), enviadoEm: 123455 }, contexto, amostra())).toBe(false);
        expect(aceitarAmostra({ ...amostra(), enviadoEm: 123457 }, contexto, amostra())).toBe(true);
    });
    it('interpola com atraso sem extrapolar nem inventar histórico antes da primeira amostra', () => {
        const a = { x: 60, y: 60, tempo: 1000, recebidaEm: 1000 };
        const b = { x: 80, y: 100, tempo: 1100, recebidaEm: 1100 };
        expect(interpolarAmostras(null, null, 100)).toBeNull();
        expect(interpolarAmostras(null, b, 1150)).toEqual({ x: 80, y: 100, tempo: 1100 });
        expect(interpolarAmostras(a, b, 1150)).toEqual({ x: 70, y: 80, tempo: 1050 });
        expect(interpolarAmostras(a, b, 1000)).toEqual({ x: 60, y: 60, tempo: 1000 });
        expect(interpolarAmostras(a, b, 9999)).toEqual({ x: 80, y: 100, tempo: 1100 });
        expect(interpolarAmostras(b, b, 1100)).toEqual({ x: 80, y: 100, tempo: 1100 });
    });
});

it('valida as amostras contra as dimensões do mapa da rodada', () => {
    const mapa = { raioBolinha: 10, largura: 200, altura: 200 };
    expect(aceitarAmostra(amostra(), contexto, null, mapa)).toBe(true);
    expect(aceitarAmostra({ ...amostra(), x: 300 }, contexto, null, mapa)).toBe(false);
});
