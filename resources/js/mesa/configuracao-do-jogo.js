export function abaDoCampo(campo) {
    if (campo === 'modo' || campo === 'prazo_vez_segundos') return 'modo';
    if (campo.startsWith('pontos_por_posicao')) return 'pontuacao';
    if (campo === 'meta_pontos' || campo === 'limite_rodadas') return 'metas';
    return null;
}

export function configuracaoDoJogo(jogo = 'labirinto', modoMetaInicial = 'nenhum') {
    const abas = [jogo, 'modo', 'pontuacao', 'metas'];

    return {
        aba: abas[0],
        modoMeta: modoMetaInicial,
        reiniciar() { this.aba = abas[0]; },
        aoMudarModoMeta($el) {
            const form = $el.closest('form');
            if (!form) return;
            const padroes = { meta_pontos: '5', limite_rodadas: '5' };
            ['meta_pontos', 'limite_rodadas'].forEach((nome) => {
                const visivel = (nome === 'meta_pontos' && this.modoMeta === 'pontos')
                    || (nome === 'limite_rodadas' && this.modoMeta === 'rodadas');
                const input = form.querySelector(`[name="${nome}"]`);
                if (!input) return;
                if (!visivel) {
                    input.value = '';
                } else if (input.value === '') {
                    input.value = padroes[nome];
                }
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },
        selecionar(aba) { if (abas.includes(aba)) this.aba = aba; },
        impedirNumeroNegativo(evento) {
            const campo = evento.target;
            if (campo?.matches?.('input[type="number"][data-numero-nao-negativo]') && ['-', 'Subtract'].includes(evento.key)) {
                evento.preventDefault();
            }
        },
        corrigirNumeroNegativo(evento) {
            const campo = evento.target;
            if (!campo?.matches?.('input[type="number"][data-numero-nao-negativo]')) return;

            const valor = Number(campo.value);
            if (campo.value !== '' && (!Number.isFinite(valor) || valor < 0)) {
                campo.value = String(Math.max(0, Number(campo.min) || 0));
                campo.dispatchEvent?.(new Event('input', { bubbles: true }));
            }
        },
        mostrarCampo(campo) {
            const aba = abaDoCampo(campo);
            if (aba) this.selecionar(aba);
        },
        mostrarErros(campos) {
            const campo = campos.find(campo => abaDoCampo(campo));
            if (campo) this.mostrarCampo(campo);
        },
        navegar(evento, atual, lista) {
            const indice = abas.indexOf(atual);
            let proxima;
            if (evento.key === 'ArrowRight') proxima = abas[(indice + 1) % abas.length];
            if (evento.key === 'ArrowLeft') proxima = abas[(indice + abas.length - 1) % abas.length];
            if (evento.key === 'Home') proxima = abas[0];
            if (evento.key === 'End') proxima = abas.at(-1);
            if (!proxima) return;
            evento.preventDefault();
            this.selecionar(proxima);
            lista.querySelector(`#aba-jogo-${proxima}`).focus();
        },
    };
}
