import { afterEach, describe, expect, it, vi } from 'vitest';
import { enviarResultadoDaPartida } from '../../../resources/js/jogos/labirinto/envio-da-partida.js';

const pedido = () => ({ destino: '/mesas/mesa/jogar/resultado', csrf: 'csrf-de-teste', tempo: 15000, jogada: 'comprovante-da-jogada', partida: 2, rodada: 3, desempate: 1, sinal: new AbortController().signal });
afterEach(() => vi.unstubAllGlobals());

describe('Envio da tentativa da partida', () => {
    it('envia o tempo, contexto e CSRF e volta diretamente para a mesa', async () => {
        const buscar = vi.fn().mockResolvedValue(new Response(JSON.stringify({ destino: '/mesas/mesa' })));
        vi.stubGlobal('fetch', buscar);
        const dados = pedido();
        expect(await enviarResultadoDaPartida(dados)).toEqual({ destino: '/mesas/mesa' });
        expect(buscar).toHaveBeenCalledWith(dados.destino, expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({ 'X-CSRF-TOKEN': dados.csrf, Accept: 'application/json' }),
            body: JSON.stringify({ tempo: 15000, jogada: 'comprovante-da-jogada', partida: 2, rodada: 3, desempate: 1, lacre: '41831133' }),
            signal: dados.sinal,
        }));
    });

    it('permite reenviar depois de falha de rede', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Falha de rede')));
        expect(await enviarResultadoDaPartida(pedido())).toMatchObject({ podeReenviar: true });
    });

    it('distingue mudanca definitiva da vez de conflito que permite reenvio', async () => {
        const buscar = vi.fn();
        vi.stubGlobal('fetch', buscar);
        for (const podeReenviar of [false, true]) {
            buscar.mockResolvedValueOnce(new Response(JSON.stringify({ mensagem: 'A mesa mudou.', pode_reenviar: podeReenviar }), { status: 409 }));
            expect(await enviarResultadoDaPartida(pedido())).toEqual({ mensagem: 'A mesa mudou.', podeReenviar });
        }
    });

    it('mostra validacao e nao insiste em sessao expirada ou acesso negado', async () => {
        const buscar = vi.fn();
        vi.stubGlobal('fetch', buscar);
        buscar.mockResolvedValueOnce(new Response(JSON.stringify({ errors: { tempo: ['Tempo inválido.'] } }), { status: 422 }));
        expect(await enviarResultadoDaPartida(pedido())).toEqual({ mensagem: 'Tempo inválido.', podeReenviar: false });
        for (const situacao of [401, 403, 404, 419]) {
            buscar.mockResolvedValueOnce(new Response('Erro', { status: situacao }));
            expect(await enviarResultadoDaPartida(pedido())).toMatchObject({ podeReenviar: false });
        }
    });

    it('permite nova confirmacao se o servidor falhar ou devolver resposta incompleta', async () => {
        const buscar = vi.fn();
        vi.stubGlobal('fetch', buscar);
        for (const situacao of [200, 500, 503]) {
            buscar.mockResolvedValueOnce(new Response('Resposta incompleta', { status: situacao }));
            expect(await enviarResultadoDaPartida(pedido())).toMatchObject({ podeReenviar: true });
        }
    });

    it('cancelamento ao navegar nao vira erro de conexao', async () => {
        const cancelar = new AbortController();
        cancelar.abort();
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(cancelar.signal.reason));
        await expect(enviarResultadoDaPartida({ ...pedido(), sinal: cancelar.signal })).rejects.toMatchObject({ name: 'AbortError' });
    });
});
