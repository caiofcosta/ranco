import { afterEach, describe, expect, it, vi } from 'vitest';
import { executarSequencia, sequenciaDasColocacoes } from '../../../resources/js/mesa/revelacao.js';

afterEach(() => vi.useRealTimers());
describe('revelacao da mesa', () => {
    it('ordena o podio de baixo para cima e agrupa empates', () => {
        expect(sequenciaDasColocacoes([1, 2, 2, 3, 4, 8])).toEqual([3, 2, 1]);
        expect(sequenciaDasColocacoes([1, 1])).toEqual([1]);
    });
    it('revela trofeus juntos, frases por ultimo e termina em 3,3 segundos', () => {
        vi.useFakeTimers();
        const revelar = vi.fn();
        const finalizar = vi.fn();
        executarSequencia([1, 2, 2, 3, 4], { revelar, finalizar });
        vi.advanceTimersByTime(3299);
        expect(revelar.mock.calls.flat()).toEqual([3, 2, 1, 'trofeus', 'frases']);
        expect(finalizar).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(finalizar).toHaveBeenCalledOnce();
    });
    it('pular cancela a sequencia e finaliza uma unica vez', () => {
        vi.useFakeTimers();
        const revelar = vi.fn();
        const finalizar = vi.fn();
        const pular = executarSequencia([1, 2, 3], { revelar, finalizar });
        vi.advanceTimersByTime(300);
        pular();
        pular();
        vi.runAllTimers();
        expect(revelar.mock.calls.flat()).toEqual([3]);
        expect(finalizar).toHaveBeenCalledOnce();
    });
    it('movimento reduzido mostra o final sem agendar animacao', () => {
        const agendar = vi.fn();
        const finalizar = vi.fn();
        const revelar = vi.fn();
        executarSequencia([1, 2], { agendar, finalizar, revelar, movimentoReduzido: true });
        expect(agendar).not.toHaveBeenCalled();
        expect(revelar).not.toHaveBeenCalled();
        expect(finalizar).toHaveBeenCalledOnce();
    });
});
