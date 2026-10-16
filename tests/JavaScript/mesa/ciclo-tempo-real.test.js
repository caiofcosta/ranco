import { describe, expect, it, vi } from 'vitest';
import { criarTempoReal } from '../../../resources/js/mesa/ciclo-tempo-real.js';

const ana = { id: 'a', nome: 'Ana' };
const bruno = { id: 'b', nome: 'Bruno' };

function criarCadeira(id) {
    return {
        dataset: { cadeira: id },
        online: false,
        toggleAttribute(nome, ligado) {
            if (nome === 'data-online') this.online = ligado;
        },
    };
}

function criarLista() {
    return {
        itens: [],
        replaceChildren(...itens) {
            this.itens = itens.map((item) => item.textContent);
        },
    };
}

function criarDocumento() {
    const pagina = { lista: null, mesaId: null };

    return {
        abrirMesa(mesaId) {
            pagina.lista = criarLista();
            pagina.mesaId = mesaId;
            return pagina.lista;
        },
        sairDaMesa() {
            pagina.lista = null;
            pagina.mesaId = null;
        },
        querySelector(seletor) {
            if (seletor === '[data-tempo-real-presenca]') return pagina.lista;
            if (seletor === '[data-tempo-real-mesa]') return pagina.mesaId ? { dataset: { mesaId: pagina.mesaId } } : null;
            return null;
        },
        createElement() {
            return { textContent: '' };
        },
    };
}

function criarEcho() {
    return {
        canais: {},
        saidas: [],
        private(nome) {
            const canal = {
                ouvintes: {},
                listen(evento, retorno) {
                    this.ouvintes[evento] = retorno;
                    return this;
                },
            };
            this.canais[nome] = canal;
            return canal;
        },
        join(nome) {
            const canal = {
                here(retorno) {
                    this.aoEstar = retorno;
                    return this;
                },
                joining(retorno) {
                    this.aoEntrar = retorno;
                    return this;
                },
                leaving(retorno) {
                    this.aoSair = retorno;
                    return this;
                },
            };
            this.canais[nome] = canal;
            return canal;
        },
        leave(nome) {
            this.saidas.push(nome);
        },
    };
}

function preparar() {
    const documento = criarDocumento();
    const echo = criarEcho();
    const avisarMesa = vi.fn();
    const conexao = {
        bind(evento, retorno) {
            this[evento] = retorno;
        },
    };
    const tempoReal = criarTempoReal({ documento, echo, avisarMesa, conexao });

    return { documento, echo, avisarMesa, conexao, tempoReal };
}

describe('ciclo do tempo real da mesa', () => {
    it('fora da mesa nao assina canais', () => {
        const { echo, tempoReal } = preparar();
        tempoReal.sincronizar();

        expect(echo.canais).toEqual({});
    });

    it('repassa o sinal da mesa para o Livewire', () => {
        const { documento, echo, avisarMesa, tempoReal } = preparar();
        documento.abrirMesa('m1');
        tempoReal.sincronizar();
        echo.canais['mesa.m1'].ouvintes['.mesa.mudou']();

        expect(avisarMesa).toHaveBeenCalledWith('m1');
    });

    it('mostra quem esta presente e acompanha entradas e saidas', () => {
        const { documento, echo, tempoReal } = preparar();
        const lista = documento.abrirMesa('m1');
        tempoReal.sincronizar();
        const presenca = echo.canais['presenca.mesa.m1'];

        presenca.aoEstar([ana]);
        presenca.aoEntrar(bruno);
        presenca.aoEntrar(bruno);
        expect(lista.itens).toEqual(['Ana', 'Bruno']);

        presenca.aoSair(ana);
        expect(lista.itens).toEqual(['Bruno']);
    });

    it('acende as cadeiras de quem esta online e apaga ao sair', () => {
        const { documento, echo, tempoReal } = preparar();
        const cadeiras = [criarCadeira('a'), criarCadeira('b'), criarCadeira('c')];
        documento.querySelectorAll = (seletor) => (seletor === '[data-cadeira]' ? cadeiras : []);
        documento.abrirMesa('m1');
        tempoReal.sincronizar();
        const presenca = echo.canais['presenca.mesa.m1'];

        presenca.aoEstar([ana]);
        presenca.aoEntrar(bruno);
        expect(cadeiras.map((item) => item.online)).toEqual([true, true, false]);

        presenca.aoSair(ana);
        expect(cadeiras.map((item) => item.online)).toEqual([false, true, false]);
    });

    it('reacende as cadeiras depois do redesenho do Livewire', () => {
        const { documento, echo, tempoReal } = preparar();
        const cadeiras = [criarCadeira('a'), criarCadeira('b')];
        documento.querySelectorAll = (seletor) => (seletor === '[data-cadeira]' ? cadeiras : []);
        documento.abrirMesa('m1');
        tempoReal.sincronizar();
        echo.canais['presenca.mesa.m1'].aoEstar([ana]);

        cadeiras.forEach((item) => { item.online = false; });
        tempoReal.reacender();

        expect(cadeiras.map((item) => item.online)).toEqual([true, false]);
    });

    it('reacender fora da mesa nao faz nada', () => {
        const { tempoReal } = preparar();

        expect(() => tempoReal.reacender()).not.toThrow();
    });

    it('sincronizar de novo na mesma pagina nao duplica a assinatura', () => {
        const { documento, echo, tempoReal } = preparar();
        const assinar = vi.spyOn(echo, 'private');
        documento.abrirMesa('m1');
        tempoReal.sincronizar();
        tempoReal.sincronizar();

        expect(assinar).toHaveBeenCalledOnce();
        expect(echo.saidas).toEqual([]);
    });

    it('sair e voltar a mesa escreve na lista nova e ignora o canal antigo', () => {
        const { documento, echo, tempoReal } = preparar();
        const antiga = documento.abrirMesa('m1');
        tempoReal.sincronizar();
        const presencaAntiga = echo.canais['presenca.mesa.m1'];

        documento.sairDaMesa();
        tempoReal.sincronizar();
        expect(echo.saidas).toEqual(['mesa.m1', 'presenca.mesa.m1']);

        const nova = documento.abrirMesa('m1');
        tempoReal.sincronizar();
        echo.canais['presenca.mesa.m1'].aoEstar([ana]);
        presencaAntiga.aoEstar([bruno]);

        expect(nova.itens).toEqual(['Ana']);
        expect(antiga.itens).toEqual([]);
    });

    it('reconexao rele a mesa, mas a primeira conexao nao', () => {
        const { documento, avisarMesa, conexao, tempoReal } = preparar();
        documento.abrirMesa('m1');
        tempoReal.sincronizar();

        conexao.connected();
        expect(avisarMesa).not.toHaveBeenCalled();

        conexao.connected();
        expect(avisarMesa).toHaveBeenCalledOnce();
        expect(avisarMesa).toHaveBeenCalledWith('m1');
    });
});
