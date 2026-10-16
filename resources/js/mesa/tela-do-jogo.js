import { consultarPrazoDaTela } from './prazo-da-jogada.js';

let atualizandoPrazo = false;
let geracaoPrazo = 0;
let destinoExpirado;
let faixaAnterior;
let vezAnterior;
function atualizarPrazo() {
    const painel = document.querySelector('[data-prazo-da-jogada]');
    if (!painel) return;
    const faixa = painel.querySelector('[data-faixa-jogadores]');
    const atual = faixa?.querySelector('[aria-current="step"]');
    if (atual && (faixa !== faixaAnterior || atual.dataset.participante !== vezAnterior)) {
        const primeiraExibicao = !faixaAnterior;
        faixaAnterior = faixa;
        vezAnterior = atual.dataset.participante;
        const esquerda = faixa.scrollLeft + atual.getBoundingClientRect().left - faixa.getBoundingClientRect().left;
        faixa.scrollTo({ left: Math.max(0, esquerda), behavior: primeiraExibicao || window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    }
    const estado = consultarPrazoDaTela();
    const contagem = painel.querySelector('[data-contagem-prazo]');
    const iniciada = painel.dataset.iniciada === '1';
    const mostrar = estado.segundos !== null && estado.preparacao === 0 && (!iniciada || estado.avisoFinal);
    contagem.hidden = !mostrar;
    if (mostrar) {
        contagem.querySelector('[data-segundos-prazo]').textContent = `${estado.segundos}s`;
        contagem.classList.toggle('text-danger', iniciada || estado.segundos <= 15);
        if (iniciada) {
            contagem.querySelector('[data-rotulo-prazo]').textContent = painel.dataset.fase === 'jogar' ? 'Conclua sua jogada em' : 'Esta jogada termina em';
            contagem.querySelector('[data-instrucao-prazo]').textContent = 'Quando o prazo acabar, a tentativa será interrompida e a vez será passada.';
        }
    }
    const preparacao = document.querySelector('[data-preparacao]');
    if (preparacao) {
        preparacao.hidden = estado.preparacao === 0;
        preparacao.querySelector('[data-numero-preparacao]').textContent = estado.preparacao;
        preparacao.querySelector('[data-anel-preparacao]').style.strokeDashoffset = 100 - estado.anel;
    }
    if (estado.expirou && destinoExpirado !== painel.dataset.expira) {
        destinoExpirado = painel.dataset.expira;
        window.location.assign(painel.dataset.destino);
    }
}
function ligarPrazo() {
    if (atualizandoPrazo) return;
    atualizandoPrazo = true;
    const geracao = ++geracaoPrazo;
    const repetir = () => {
        if (!atualizandoPrazo || geracao !== geracaoPrazo) return;
        atualizarPrazo();
        requestAnimationFrame(repetir);
    };
    repetir();
}
document.addEventListener('livewire:navigated', ligarPrazo);
document.addEventListener('livewire:navigating', () => { atualizandoPrazo = false; geracaoPrazo++; destinoExpirado = null; });
window.addEventListener('pagehide', () => { atualizandoPrazo = false; geracaoPrazo++; });
ligarPrazo();
