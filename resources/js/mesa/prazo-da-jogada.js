export function criarRelogioDoPrazo({ agoraEm, expiraEm, liberaEm, tempo = () => performance.now() }) {
    const referencia = tempo();
    const servidor = Date.parse(agoraEm);
    const fim = Date.parse(expiraEm);
    const libera = Date.parse(liberaEm);
    return () => {
        const agora = servidor + tempo() - referencia;
        const restante = Number.isFinite(fim) ? Math.max(0, fim - agora) : null;
        const preparacao = Number.isFinite(libera) ? Math.max(0, libera - agora) : 0;
        return {
            segundos: restante === null ? null : Math.ceil(restante / 1000),
            expirou: restante === 0,
            avisoFinal: restante !== null && restante <= 30000 && preparacao === 0,
            preparacao: Math.ceil(preparacao / 1000),
            anel: Math.min(100, preparacao / 3000 * 100),
            podeMover: preparacao === 0 && restante !== 0,
        };
    };
}

let assinatura;
let relogio;
export function consultarPrazoDaTela() {
    const dados = document.querySelector('[data-prazo-da-jogada]')?.dataset;
    if (!dados) return { podeMover: true, preparacao: 0, segundos: null, expirou: false, anel: 0 };
    const nova = JSON.stringify([dados.agora, dados.expira, dados.libera]);
    if (nova !== assinatura) {
        assinatura = nova;
        relogio = criarRelogioDoPrazo({ agoraEm: dados.agora, expiraEm: dados.expira, liberaEm: dados.libera });
    }
    return relogio();
}
