export function sequenciaDasColocacoes(colocacoes) {
    return [...new Set(colocacoes)].filter(posicao => posicao >= 1 && posicao <= 3).sort((a, b) => b - a);
}

export function executarSequencia(colocacoes, { revelar, finalizar, movimentoReduzido = false, agendar = setTimeout, cancelar = clearTimeout }) {
    let terminou = false;
    const temporizadores = [];
    const pular = () => {
        if (terminou) return;
        terminou = true;
        temporizadores.forEach(cancelar);
        finalizar();
    };
    if (movimentoReduzido) {
        pular();
        return pular;
    }
    const ordem = sequenciaDasColocacoes(colocacoes);
    ordem.forEach((posicao, indice) => temporizadores.push(agendar(() => revelar(posicao), 300 + indice * 600)));
    const trofeuEm = 300 + ordem.length * 600;
    temporizadores.push(agendar(() => revelar('trofeus'), trofeuEm));
    temporizadores.push(agendar(() => revelar('frases'), trofeuEm + 600));
    temporizadores.push(agendar(pular, 3300));
    return pular;
}

export function iniciarRevelacao(palco) {
    if (palco.dataset.iniciada) return;
    palco.dataset.iniciada = '1';
    const preferencia = window.matchMedia('(prefers-reduced-motion: reduce)');
    const titulo = palco.querySelector('[data-titulo-revelacao]');
    const botao = palco.querySelector('[data-pular]');
    const anuncio = palco.querySelector('[data-anuncio]');
    const linhas = [...palco.querySelectorAll('[data-posicao]')];
    const grupos = new Map();
    for (const posicao of sequenciaDasColocacoes(linhas.map(linha => Number(linha.dataset.posicao)))) {
        grupos.set(posicao, linhas.filter(linha => Number(linha.dataset.posicao) === posicao));
    }
    grupos.set('trofeus', [...palco.querySelectorAll('[data-trofeu]')]);
    grupos.set('frases', [...palco.querySelectorAll('[data-frase]')]);
    const elementos = [...grupos.values()].flat();
    const anunciar = () => {
        const nomes = linhas.filter(linha => linha.dataset.posicao === '1').map(linha => linha.querySelector('[data-nome]').textContent.trim());
        anuncio.textContent = nomes.length ? `Primeiro lugar: ${nomes.join(', ')}.` : '';
    };
    if (preferencia.matches) {
        anunciar();
        return;
    }
    elementos.forEach(elemento => elemento.classList.add('revelacao-aguardando'));
    palco.classList.add('revelacao-em-curso');
    botao.hidden = false;
    const aoClicar = () => pular();
    const aoTeclar = evento => {
        if (['Escape', 'Enter', ' '].includes(evento.key)) {
            evento.preventDefault();
            pular();
        }
    };
    const aoReduzir = evento => { if (evento.matches) pular(); };
    const pular = executarSequencia([...grupos.keys()].filter(chave => typeof chave === 'number'), {
        revelar: chave => grupos.get(chave).forEach(elemento => {
            elemento.classList.remove('revelacao-aguardando');
            elemento.classList.add('revelacao-entrada');
        }),
        finalizar: () => {
            palco.classList.remove('revelacao-em-curso');
            elementos.forEach(elemento => elemento.classList.remove('revelacao-aguardando', 'revelacao-entrada'));
            botao.hidden = true;
            palco.removeEventListener('click', aoClicar);
            document.removeEventListener('keydown', aoTeclar);
            preferencia.removeEventListener('change', aoReduzir);
            titulo.focus({ preventScroll: true });
            anunciar();
        },
    });
    palco.addEventListener('click', aoClicar);
    document.addEventListener('keydown', aoTeclar);
    preferencia.addEventListener('change', aoReduzir);
    botao.focus({ preventScroll: true });
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-revelacao]').forEach(iniciarRevelacao);
}
