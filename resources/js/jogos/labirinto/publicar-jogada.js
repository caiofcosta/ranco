import { montarAmostra } from './transmissao.js';
import { mapaPadrao } from './mapa.js';

/** Um canal privado da mesa; falhas visuais nunca impedem o envio oficial do resultado. */
export function publicarJogada(echo, contexto, mapa = mapaPadrao) {
    const nome = `mesa.${contexto.mesa}`;
    const canal = echo.private(nome);
    // O painel pode ter inscrito o mesmo canal antes de carregar o Phaser.
    let pronta = canal.subscription?.subscribed === true;
    let estado = { posicao: mapa.inicio, tempo: 0, tipo: 'inicio' };
    const enviar = (tipo = estado.tipo) => {
        if (!pronta) return;
        try {
            canal.whisper('labirinto.amostra', montarAmostra(contexto, estado.posicao, estado.tempo, tipo, Date.now()));
        } catch {
            // Desconexão não interfere nos controles, no cronômetro nem no POST de resultado.
        }
    };
    canal.subscribed(() => { pronta = true; enviar('inicio'); });
    const intervalo = setInterval(enviar, 100);
    return {
        observar(posicao, tempo, chegou) {
            estado = { posicao, tempo, tipo: chegou ? 'chegada' : 'posicao' };
            if (chegou) enviar();
        },
        encerrar() {
            pronta = false;
            clearInterval(intervalo);
            echo.leave(nome);
        },
    };
}
