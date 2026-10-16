// Livewire restaura scroll ao voltar pelo histórico. Nas trocas entre as cinco
// áreas, guardamos também a posição para a navegação por toque na barra.
const areas = new Map([['/dashboard', 'inicio'], ['/inicio', 'inicio'], ['/pedidos', 'pedidos'], ['/jogar-juntos', 'jogar-juntos'], ['/jogar', 'jogar-solo'], ['/mais', 'mais']]);
const mobile = () => window.matchMedia('(width < 64rem)').matches;
const memoria = new Map();
let destinoPelaBarra = null;

function destacarContexto() {
    if (!mobile()) return;
    const area = areas.get(location.pathname);
    if (!area) return;
    const ativa = area;
    document.querySelectorAll('[data-mobile-destino]').forEach(link => {
        if (link.dataset.mobileDestino === ativa) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });
}

document.addEventListener('click', evento => {
    if (!mobile() || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
    const link = evento.target.closest('[data-mobile-destino], [data-mobile-atalho]');
    if (!link) return;
    const area = areas.get(location.pathname);
    if (area) memoria.set(area, window.scrollY);
    destinoPelaBarra = link.dataset.mobileDestino ?? link.dataset.mobileAtalho;
});
document.addEventListener('livewire:navigated', () => {
    destacarContexto();
    const destino = destinoPelaBarra;
    destinoPelaBarra = null;
    if (!mobile() || !destino || areas.get(location.pathname) !== destino) return;
    requestAnimationFrame(() => requestAnimationFrame(() => window.scrollTo(0, memoria.get(destino) ?? 0)));
});
window.addEventListener('resize', destacarContexto);
