import { describe, expect, it } from 'vitest';
import { lacrarTempo } from '../../../resources/js/mesa/lacre-do-tempo.js';

describe('lacre do tempo enviado', () => {
    it('calcula o mesmo valor do servidor', () => {
        // Mesmo valor conferido em tests/Unit/Mesa/LacreDoTempoTest.php.
        expect(lacrarTempo('comprovante-da-jogada', 12909)).toBe('ec3a26e8');
    });

    it('muda quando o tempo ou a jogada mudam', () => {
        const lacre = lacrarTempo('comprovante-da-jogada', 12909);
        expect(lacrarTempo('comprovante-da-jogada', 9000)).not.toBe(lacre);
        expect(lacrarTempo('outra-jogada', 12909)).not.toBe(lacre);
        expect(lacrarTempo('comprovante-da-jogada', 12909)).toMatch(/^[0-9a-f]{8}$/);
    });
});
