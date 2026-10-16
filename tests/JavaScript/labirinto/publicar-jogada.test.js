import { afterEach, describe, expect, it, vi } from 'vitest';
import { publicarJogada } from '../../../resources/js/jogos/labirinto/publicar-jogada.js';
const contexto = { mesa: 'm', partida: 1, rodada: 1, desempate: 0, participante: 'local-1' };
afterEach(() => vi.useRealTimers());

describe('publicação da jogada', () => {
    it('só transmite após inscrição, a 10 Hz, com início, chegada e limpeza', () => {
        vi.useFakeTimers();
        let inscrito;
        const canal = { subscribed: (acao) => { inscrito = acao; }, whisper: vi.fn() };
        const echo = { private: vi.fn(() => canal), leave: vi.fn() };
        const envio = publicarJogada(echo, contexto);
        vi.advanceTimersByTime(200);
        expect(canal.whisper).not.toHaveBeenCalled();
        inscrito();
        expect(canal.whisper.mock.calls[0][1].tipo).toBe('inicio');
        envio.observar({ x: 80, y: 60 }, 1000, false);
        vi.advanceTimersByTime(1000);
        expect(canal.whisper).toHaveBeenCalledTimes(11);
        expect(canal.whisper.mock.lastCall[1]).toMatchObject({ participante: 'local-1', x: 80, tempo: 1000, tipo: 'posicao' });
        envio.observar({ x: 460, y: 460 }, 12000, true);
        expect(canal.whisper.mock.lastCall[1].tipo).toBe('chegada');
        envio.encerrar();
        vi.advanceTimersByTime(1000);
        expect(canal.whisper).toHaveBeenCalledTimes(12);
        expect(echo.leave).toHaveBeenCalledWith('mesa.m');
    });
    it('uma falha de conexão não interrompe a conclusão do jogo', () => {
        vi.useFakeTimers();
        let inscrito;
        const canal = { subscribed: (acao) => { inscrito = acao; }, whisper: () => { throw new Error('desconectado'); } };
        const envio = publicarJogada({ private: () => canal, leave: vi.fn() }, contexto);
        expect(() => inscrito()).not.toThrow();
        expect(() => envio.observar({ x: 460, y: 460 }, 12000, true)).not.toThrow();
        envio.encerrar();
    });
});

it('transmite quando o painel já inscreveu o canal da mesa', () => {
    vi.useFakeTimers();
    const canal = { subscription: { subscribed: true }, subscribed: vi.fn(), whisper: vi.fn() };
    const envio = publicarJogada({ private: () => canal, leave: vi.fn() }, contexto);
    vi.advanceTimersByTime(100);
    expect(canal.whisper).toHaveBeenCalledTimes(1);
    envio.encerrar();
});

it('a primeira amostra usa o início do mapa sorteado na rodada', () => {
    vi.useFakeTimers();
    let inscrito;
    const canal = { subscribed: (acao) => { inscrito = acao; }, whisper: vi.fn() };
    const envio = publicarJogada({ private: () => canal, leave: vi.fn() }, contexto, { inicio: { x: 420, y: 100 } });
    inscrito();
    expect(canal.whisper.mock.lastCall[1]).toMatchObject({ x: 420, y: 100, tempo: 0, tipo: 'inicio' });
    envio.encerrar();
});
