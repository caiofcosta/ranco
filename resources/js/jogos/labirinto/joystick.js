import { direcaoDoJoystick } from './controles.js';

export function criarJoystick(tabuleiro) {
    // Remove uma cópia eventualmente restaurada pelo histórico do Livewire.
    tabuleiro.parentElement.querySelector('[data-joystick]')?.remove();
    const area = document.createElement('div');
    area.className = 'labirinto-controle';
    area.dataset.joystick = '';
    area.innerHTML = `<div class="labirinto-joystick" role="group" aria-label="Alavanca do Labirinto: arraste com o dedo ou mouse para mover. Solte para parar.">
        <span class="labirinto-seta cima" aria-hidden="true">↑</span><span class="labirinto-seta baixo" aria-hidden="true">↓</span>
        <span class="labirinto-seta esquerda" aria-hidden="true">←</span><span class="labirinto-seta direita" aria-hidden="true">→</span>
        <svg class="labirinto-haste" viewBox="0 0 148 148" aria-hidden="true">
            <line class="labirinto-haste-sombra" x1="74" y1="90" x2="74" y2="58" />
            <line class="labirinto-haste-metal" x1="74" y1="87" x2="74" y2="55" />
            <line class="labirinto-haste-luz" x1="72" y1="85" x2="72" y2="55" />
        </svg>
        <span class="labirinto-alavanca" aria-hidden="true"><span class="labirinto-manopla"></span></span>
    </div><p>Arraste a alavanca · solte para parar</p>`;
    tabuleiro.after(area);
    const base = area.querySelector('.labirinto-joystick');
    const alavanca = area.querySelector('.labirinto-alavanca');
    const hastes = area.querySelectorAll('.labirinto-haste line');
    function desenhar(x, y) {
        // Projeta a inclinação de uma haste curta em torno do encaixe fixo.
        // O alcance do gesto continua em 48 px; a manopla não segue o dedo 1:1.
        const inclinacao = Math.min(1, Math.hypot(x, y) / 48);
        const visualX = x / 48 * 22;
        const visualY = y / 48 * 10 + 12 * inclinacao ** 2;
        alavanca.style.transform = `translate(${visualX}px, ${visualY}px)`;
        hastes.forEach((haste, indice) => {
            haste.setAttribute('x2', (indice === 2 ? 72 : 74) + visualX);
            haste.setAttribute('y2', (indice === 0 ? 58 : 55) + visualY);
        });
    }
    const eventos = new AbortController();
    const opcoes = { signal: eventos.signal };
    let ponteiro = null;
    let direcao = { x: 0, y: 0 };
    function parar() {
        const anterior = ponteiro;
        ponteiro = null;
        direcao = { x: 0, y: 0 };
        desenhar(0, 0);
        base.classList.remove('ativo');
        if (anterior !== null && base.hasPointerCapture(anterior)) base.releasePointerCapture(anterior);
    }
    function atualizar(evento) {
        if (evento.pointerId !== ponteiro) return;
        const retangulo = base.getBoundingClientRect();
        const x = evento.clientX - retangulo.left - retangulo.width / 2;
        const y = evento.clientY - retangulo.top - retangulo.height / 2;
        const escala = Math.min(1, 48 / (Math.hypot(x, y) || 1));
        direcao = direcaoDoJoystick(x, y);
        desenhar(x * escala, y * escala);
    }
    base.addEventListener('pointerdown', evento => {
        if (ponteiro !== null || evento.button !== 0) return;
        evento.preventDefault();
        ponteiro = evento.pointerId;
        base.setPointerCapture(ponteiro);
        base.classList.add('ativo');
        tabuleiro.querySelector('canvas')?.focus({ preventScroll: true });
        atualizar(evento);
    }, opcoes);
    base.addEventListener('pointermove', atualizar, opcoes);
    for (const nome of ['pointerup', 'pointercancel', 'lostpointercapture']) {
        base.addEventListener(nome, evento => { if (evento.pointerId === ponteiro) parar(); }, opcoes);
    }
    window.addEventListener('blur', parar, opcoes);
    document.addEventListener('visibilitychange', parar, opcoes);
    return {
        get direcao() { return direcao; },
        destruir() { parar(); eventos.abort(); area.remove(); },
    };
}
