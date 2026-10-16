import { describe, expect, it } from 'vitest';
import { ouvirAvisosDaConta } from '../../../resources/js/mesa/aviso-da-conta.js';

function prepararEcho() {
    const ouvintes = {};
    return {
        ouvintes,
        private(canal) {
            return {
                listen(evento, funcao) {
                    ouvintes[`${canal}|${evento}`] = funcao;
                    return this;
                },
            };
        },
    };
}

describe('avisos da propria conta', () => {
    it('ouve o canal privado da conta e reage quando ela fica invisivel', () => {
        const echo = prepararEcho();
        let chamadas = 0;

        const canal = ouvirAvisosDaConta({ echo, pessoaId: '7', aoFicarInvisivel: () => { chamadas += 1; } });
        echo.ouvintes['App.Models.User.7|.pessoa.ficou-invisivel']();

        expect(canal).toBe('App.Models.User.7');
        expect(chamadas).toBe(1);
    });

    it('sem conta na pagina nao escuta nada', () => {
        const echo = prepararEcho();

        expect(ouvirAvisosDaConta({ echo, pessoaId: undefined, aoFicarInvisivel: () => {} })).toBeNull();
        expect(Object.keys(echo.ouvintes)).toEqual([]);
    });
});
