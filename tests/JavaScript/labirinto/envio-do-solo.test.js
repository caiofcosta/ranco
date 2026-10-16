import { afterEach, describe, expect, it, vi } from 'vitest';
import { enviarResultadoDoSolo } from '../../../resources/js/jogos/labirinto/envio-do-solo.js';

const pedido = () => ({ destino: '/jogar/labirinto/resultado', csrf: 'csrf-de-teste', tempo: 15000, tentativa: 'uuid-da-tentativa', sinal: new AbortController().signal });
const responder = (corpo, status = 200) => vi.fn().mockResolvedValue(new Response(JSON.stringify(corpo), { status }));
afterEach(() => vi.unstubAllGlobals());

describe('Envio do tempo do solo', () => {
    it('envia tempo, tentativa e CSRF e segue para o resultado', async () => {
        const buscar = responder({ destino: '/jogar/labirinto/resultado?tentativa=x' });
        vi.stubGlobal('fetch', buscar);
        const dados = pedido();

        expect(await enviarResultadoDoSolo(dados)).toEqual({ destino: '/jogar/labirinto/resultado?tentativa=x' });
        expect(buscar).toHaveBeenCalledWith(dados.destino, expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({ 'X-CSRF-TOKEN': 'csrf-de-teste' }),
            body: JSON.stringify({ tempo: 15000, tentativa: 'uuid-da-tentativa' }),
        }));
    });

    it('jogada expirada ou com tempo recusado nao oferece reenvio e orienta jogar de novo', async () => {
        vi.stubGlobal('fetch', responder({ errors: { tempo: ['Esta tentativa expirou. Inicie outro jogo.'] } }, 422));
        const resultado = await enviarResultadoDoSolo(pedido());

        expect(resultado.podeReenviar).toBe(false);
        expect(resultado.mensagem).toContain('Esta tentativa expirou.');
        expect(resultado.mensagem).toContain('Jogar de novo');
    });

    it('jogada apagada, concluida com outro tempo ou sessao vencida nao oferecem reenvio', async () => {
        for (const status of [404, 409, 401, 419]) {
            vi.stubGlobal('fetch', responder({ message: 'Motivo.' }, status));
            expect(await enviarResultadoDoSolo(pedido())).toMatchObject({ podeReenviar: false });
        }
    });

    it('falha de conexao, erro do servidor ou excesso de pedidos permitem reenviar', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Falha de rede')));
        expect(await enviarResultadoDoSolo(pedido())).toMatchObject({ podeReenviar: true });
        for (const status of [500, 503, 429]) {
            vi.stubGlobal('fetch', responder({}, status));
            expect(await enviarResultadoDoSolo(pedido())).toMatchObject({ podeReenviar: true });
        }
    });
});
