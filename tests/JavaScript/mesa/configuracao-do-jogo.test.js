import { describe, expect, it, vi } from 'vitest';
import { configuracaoDoJogo } from '../../../resources/js/mesa/configuracao-do-jogo.js';

describe('abas da configuração do jogo', () => {
    it('abre a aba do primeiro erro de campo e ignora erros gerais', () => {
        const tela = configuracaoDoJogo();
        tela.mostrarErros(['partida', 'pontos_por_posicao.1', 'meta_pontos']);
        expect(tela.aba).toBe('pontuacao');
        tela.mostrarCampo('prazo_vez_segundos');
        expect(tela.aba).toBe('modo');
        tela.mostrarCampo('limite_rodadas');
        expect(tela.aba).toBe('metas');
        tela.mostrarCampo('pontos_por_posicao[2]');
        expect(tela.aba).toBe('pontuacao');
        tela.reiniciar();
        expect(tela.aba).toBe('labirinto');
    });

    it('navega com setas, Home e End, incluindo retorno entre extremidades', () => {
        const tela = configuracaoDoJogo();
        const focus = vi.fn();
        const lista = { querySelector: vi.fn(() => ({ focus })) };
        for (const [key, atual, destino] of [
            ['ArrowLeft', 'labirinto', 'metas'],
            ['ArrowRight', 'metas', 'labirinto'],
            ['End', 'modo', 'metas'],
            ['Home', 'pontuacao', 'labirinto'],
        ]) {
            const evento = { key, preventDefault: vi.fn() };
            tela.navegar(evento, atual, lista);
            expect(tela.aba).toBe(destino);
            expect(lista.querySelector).toHaveBeenLastCalledWith(`#aba-jogo-${destino}`);
            expect(evento.preventDefault).toHaveBeenCalledOnce();
        }
        expect(focus).toHaveBeenCalledTimes(4);
        tela.navegar({ key: 'Tab', preventDefault: vi.fn() }, 'labirinto', lista);
        expect(focus).toHaveBeenCalledTimes(4);
    });

    it('aplica a meta padrão de cinco pontos ou cinco rodadas ao trocar o modo de término', () => {
        const tela = configuracaoDoJogo('labirinto', 'pontos');
        const campos = {
            meta_pontos: { value: '5', dispatchEvent: vi.fn() },
            limite_rodadas: { value: '', dispatchEvent: vi.fn() },
        };
        const formulario = {
            querySelector: vi.fn((seletor) => campos[seletor.match(/"(.*)"/)[1]]),
        };
        const radio = { closest: vi.fn(() => formulario) };

        tela.modoMeta = 'rodadas';
        tela.aoMudarModoMeta(radio);
        expect(campos.meta_pontos.value).toBe('');
        expect(campos.limite_rodadas.value).toBe('5');

        tela.modoMeta = 'pontos';
        tela.aoMudarModoMeta(radio);
        expect(campos.meta_pontos.value).toBe('5');
        expect(campos.limite_rodadas.value).toBe('');
    });

    it('impede e corrige valores negativos nos campos numéricos', () => {
        const tela = configuracaoDoJogo('labirinto');
        const campo = {
            value: '-4',
            min: '0',
            matches: (seletor) => seletor === 'input[type="number"][data-numero-nao-negativo]',
            dispatchEvent: vi.fn(),
        };
        const tecla = { target: campo, key: '-', preventDefault: vi.fn() };

        tela.impedirNumeroNegativo(tecla);
        tela.corrigirNumeroNegativo({ target: campo });

        expect(tecla.preventDefault).toHaveBeenCalledOnce();
        expect(campo.value).toBe('0');
        expect(campo.dispatchEvent).toHaveBeenCalledOnce();
    });
});
