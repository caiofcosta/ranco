import { describe, expect, it } from 'vitest';
import { incluirPresenca, removerPresenca } from '../../../resources/js/mesa/presenca.js';

const ana = { id: 'a', nome: 'Ana' };
const bruno = { id: 'b', nome: 'Bruno' };

describe('presenca da mesa', () => {
    it('inclui quem entra sem duplicar quem ja estava', () => {
        expect(incluirPresenca([ana], bruno)).toEqual([ana, bruno]);
        expect(incluirPresenca([ana], { ...ana, nome: 'Ana Maria' })).toEqual([{ id: 'a', nome: 'Ana Maria' }]);
    });

    it('remove quem sai pelo identificador e ignora quem nao estava', () => {
        expect(removerPresenca([ana, bruno], 'a')).toEqual([bruno]);
        expect(removerPresenca([ana], 'x')).toEqual([ana]);
    });
});
