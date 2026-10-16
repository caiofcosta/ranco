import { describe, expect, it } from 'vitest';
import { criarPresencaDeConvidado } from '../../../resources/js/mesa/presenca-de-convidado.js';

function preparar() {
    const pagina = { convites: [] };
    const documento = {
        querySelectorAll: (seletor) => (seletor === '[data-presenca-convite]'
            ? pagina.convites.map((mesaId) => ({ dataset: { presencaConvite: mesaId } }))
            : []),
    };
    const echo = {
        entradas: [],
        saidas: [],
        join(nome) {
            this.entradas.push(nome);
            return {};
        },
        leave(nome) {
            this.saidas.push(nome);
        },
    };

    return { pagina, echo, presenca: criarPresencaDeConvidado({ documento, echo }) };
}

describe('presenca de quem foi convidado', () => {
    it('entra na presenca de cada mesa com convite pendente, uma vez so', () => {
        const { pagina, echo, presenca } = preparar();
        pagina.convites = ['m1', 'm2'];

        presenca.sincronizar();
        presenca.sincronizar();

        expect(echo.entradas).toEqual(['presenca.mesa.m1', 'presenca.mesa.m2']);
    });

    it('sai da mesa cujo convite deixou a tela', () => {
        const { pagina, echo, presenca } = preparar();
        pagina.convites = ['m1', 'm2'];
        presenca.sincronizar();

        pagina.convites = ['m2'];
        presenca.sincronizar();

        expect(echo.saidas).toEqual(['presenca.mesa.m1']);
        expect(echo.entradas).toEqual(['presenca.mesa.m1', 'presenca.mesa.m2']);
    });

    it('sem convites nao entra em nada', () => {
        const { echo, presenca } = preparar();
        presenca.sincronizar();

        expect(echo.entradas).toEqual([]);
    });

    it('sai de todas as presencas quando a pessoa fica invisivel', () => {
        const { pagina, echo, presenca } = preparar();
        pagina.convites = ['m1', 'm2'];
        presenca.sincronizar();

        presenca.sairDeTodas();
        presenca.sincronizar();

        expect(echo.saidas).toEqual(['presenca.mesa.m1', 'presenca.mesa.m2']);
        expect(echo.entradas).toEqual(['presenca.mesa.m1', 'presenca.mesa.m2', 'presenca.mesa.m1', 'presenca.mesa.m2']);
    });
});
