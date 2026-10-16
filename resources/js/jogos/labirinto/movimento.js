import { mapaPadrao } from './mapa.js';

export function mover(posicao, direcao, intervalo, mapa = mapaPadrao) {
    const { colide, velocidade } = mapa;
    const intensidade = Math.hypot(direcao.x, direcao.y);
    if (!intensidade || intervalo <= 0) return { ...posicao };
    // Limita atrasos de renderização e subdivide para nunca atravessar uma parede.
    const distancia = velocidade * Math.min(intervalo, 50) / 1000;
    const passos = Math.ceil(distancia / 2);
    const deslocamentoX = direcao.x / Math.max(1, intensidade) * distancia / passos;
    const deslocamentoY = direcao.y / Math.max(1, intensidade) * distancia / passos;
    const proxima = { ...posicao };
    for (let passo = 0; passo < passos; passo++) {
        if (!colide({ x: proxima.x + deslocamentoX, y: proxima.y })) proxima.x += deslocamentoX;
        if (!colide({ x: proxima.x, y: proxima.y + deslocamentoY })) proxima.y += deslocamentoY;
    }
    return proxima;
}
