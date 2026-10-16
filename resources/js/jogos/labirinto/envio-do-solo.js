const JOGAR_DE_NOVO = 'Use “Jogar de novo” para começar outra.';

/**
 * Envia o tempo da jogada solo. Só falha passageira (conexão, servidor, excesso de pedidos)
 * permite reenviar; jogada expirada ou apagada, tempo recusado e sessão vencida não mudam
 * com outra tentativa de envio.
 */
export async function enviarResultadoDoSolo({ destino, csrf, tempo, tentativa, sinal }) {
    let resposta;
    try {
        resposta = await fetch(destino, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ tempo, tentativa }),
            signal: sinal,
        });
    } catch (erro) {
        if (sinal.aborted) throw erro;
        return { mensagem: 'Confira a conexão e tente novamente.', podeReenviar: true };
    }

    const dados = await resposta.json().catch(() => ({}));
    if (resposta.ok && typeof dados.destino === 'string') return { destino: dados.destino };
    if (resposta.status === 401 || resposta.status === 419) {
        return { mensagem: 'Sua sessão expirou. Entre de novo e jogue outra vez.', podeReenviar: false };
    }
    if (resposta.status === 404) {
        return { mensagem: `Esta jogada não existe mais. ${JOGAR_DE_NOVO}`, podeReenviar: false };
    }
    if (resposta.status === 409 || resposta.status === 422) {
        const motivo = dados.errors?.tempo?.[0] ?? dados.message ?? 'Esta jogada não pôde ser validada.';
        return { mensagem: `${motivo} ${JOGAR_DE_NOVO}`, podeReenviar: false };
    }

    return { mensagem: 'Tente enviar novamente.', podeReenviar: true };
}
