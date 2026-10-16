import { mapaPadrao } from './mapa.js';

const camposContexto = ['mesa', 'partida', 'rodada', 'desempate', 'participante'];
const campos = [...camposContexto, 'versao', 'tipo', 'enviadoEm', 'x', 'y', 'tempo'];

/** Contrato exclusivamente visual. Não contém comprovante nem resultado oficial. */
export function montarAmostra(contexto, posicao, tempo, tipo, enviadoEm) {
    return {
        ...Object.fromEntries(camposContexto.map((campo) => [campo, contexto[campo]])),
        versao: 1, tipo, enviadoEm,
        x: Math.round(posicao.x * 100) / 100,
        y: Math.round(posicao.y * 100) / 100,
        tempo: Math.round(tempo),
    };
}

export function validarAmostra(dados, contexto, mapa = mapaPadrao) {
    if (!dados || typeof dados !== 'object' || Array.isArray(dados)) return false;
    if (Object.keys(dados).length !== campos.length || !campos.every((campo) => Object.hasOwn(dados, campo))) return false;
    if (dados.versao !== 1 || !['inicio', 'posicao', 'chegada'].includes(dados.tipo)) return false;
    if (!camposContexto.every((campo) => dados[campo] === contexto[campo])) return false;
    if (!['mesa', 'participante'].every((campo) => typeof dados[campo] === 'string' && dados[campo].length > 0 && dados[campo].length <= 100)) return false;
    if (!['partida', 'rodada', 'desempate', 'enviadoEm', 'tempo'].every((campo) => Number.isSafeInteger(dados[campo]))) return false;
    if (dados.partida < 1 || dados.rodada < 1 || dados.desempate < 0 || dados.enviadoEm < 0 || dados.tempo < 0 || dados.tempo > 86400000) return false;
    return Number.isFinite(dados.x) && Number.isFinite(dados.y)
        && dados.x >= mapa.raioBolinha && dados.x <= mapa.largura - mapa.raioBolinha
        && dados.y >= mapa.raioBolinha && dados.y <= mapa.altura - mapa.raioBolinha;
}

/** Relógio local de recepção: atraso de uma amostra, sem extrapolar durante quedas. */
export function interpolarAmostras(anterior, atual, agora, atraso = 100) {
    if (!atual) return null;
    if (!anterior) return { x: atual.x, y: atual.y, tempo: atual.tempo };
    const duracao = atual.recebidaEm - anterior.recebidaEm;
    const fracao = duracao <= 0 ? 1 : Math.max(0, Math.min(1, (agora - atraso - anterior.recebidaEm) / duracao));
    return Object.fromEntries(['x', 'y', 'tempo'].map((campo) => [campo, anterior[campo] + (atual[campo] - anterior[campo]) * fracao]));
}

export function aceitarAmostra(dados, contexto, ultima, mapa = mapaPadrao) {
    return validarAmostra(dados, contexto, mapa) && (!ultima || dados.enviadoEm > ultima.enviadoEm);
}
