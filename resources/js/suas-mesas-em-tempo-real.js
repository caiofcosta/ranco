/**
 * "Suas mesas" da Início desktop, sem recarregar a página. Quando chega um aviso que muda a lista
 * (convite, participação, visibilidade) ou o estado de uma mesa que aparece nela (a vez, por
 * exemplo), pede o bloco de novo ao servidor e troca só ele. Usa os canais e eventos que já existem.
 */
export function ouvirSuasMesas({ pessoaId, documento = document }) {
    if (!pessoaId) return;

    let mesasLigadas = new Set();
    let temporizador = null;
    let pedido = null;

    const bloco = () => documento.querySelector('[data-suas-mesas]');
    // Escondido (mobile) não atualiza: a lista só existe na tela de desktop.
    const visivel = (el) => el && el.offsetParent !== null;

    function agendarAtualizacao() {
        clearTimeout(temporizador);
        temporizador = setTimeout(atualizar, 250);
    }

    async function atualizar() {
        const atual = bloco();
        if (!visivel(atual)) return;

        pedido?.abort();
        pedido = new AbortController();
        try {
            const resposta = await fetch(atual.dataset.url, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                signal: pedido.signal,
            });
            if (!resposta.ok) return;

            const novo = new DOMParser().parseFromString(await resposta.text(), 'text/html')
                .querySelector('[data-suas-mesas]');
            const alvo = bloco();
            // Troca só quando mudou: senão a animação de entrada dos cartões recomeçaria à toa.
            if (novo && alvo && novo.innerHTML !== alvo.innerHTML) alvo.replaceWith(novo);
            sincronizarMesas();
        } catch (erro) {
            if (erro.name !== 'AbortError') console.error(erro);
        }
    }

    // Liga a Início ao canal de cada mesa exibida (o mesmo canal da mesa, já autorizado no servidor).
    function sincronizarMesas() {
        const atuais = new Set();
        if (visivel(bloco())) {
            for (const cartao of documento.querySelectorAll('[data-suas-mesas] [data-mesa-id]')) {
                atuais.add(cartao.dataset.mesaId);
            }
        }

        for (const mesaId of mesasLigadas) {
            if (!atuais.has(mesaId)) window.Echo.leave(`mesa.${mesaId}`);
        }
        for (const mesaId of atuais) {
            if (!mesasLigadas.has(mesaId)) {
                window.Echo.private(`mesa.${mesaId}`).listen('.mesa.mudou', agendarAtualizacao);
            }
        }
        mesasLigadas = atuais;
    }

    import('./echo.js').then(() => {
        window.Echo.private(`App.Models.User.${pessoaId}`)
            .listen('.convite.recebido', agendarAtualizacao)
            .listen('.mesas.mudaram', agendarAtualizacao)
            .listen('.pessoa.ficou-invisivel', agendarAtualizacao);

        sincronizarMesas();
        document.addEventListener('livewire:navigated', sincronizarMesas);
    });
}
