import { lacrarTempo } from '../../mesa/lacre-do-tempo.js';

/** Envia só o tempo e a identidade da vez; toda regra e pontuação ficam no servidor. */
export async function enviarResultadoDaPartida({ destino, csrf, tempo, jogada, partida, rodada, desempate, sinal }) {
    let resposta;
    try {
        resposta = await fetch(destino, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ tempo, jogada, partida, rodada, desempate, lacre: lacrarTempo(jogada, tempo) }),
            signal: sinal,
        });
    } catch (erro) {
        if (sinal.aborted) throw erro;
        return { mensagem: 'Não foi possível confirmar seu tempo. Confira a conexão e tente novamente.', podeReenviar: true };
    }

    const dados = await resposta.json().catch(() => ({}));
    if (resposta.ok && typeof dados.destino === 'string') return { destino: dados.destino };
    if (resposta.status === 401 || resposta.status === 419) {
        return { mensagem: 'Sua sessão expirou. Volte para a mesa e entre novamente.', podeReenviar: false };
    }
    if (resposta.status === 403 || resposta.status === 404) {
        return { mensagem: 'Você não tem acesso a esta jogada. Volte para a mesa.', podeReenviar: false };
    }
    if (resposta.status === 409) {
        return { mensagem: dados.mensagem ?? 'A situação da partida mudou. Volte para a mesa.', podeReenviar: dados.pode_reenviar === true };
    }
    if (resposta.status === 422) {
        return { mensagem: dados.errors?.tempo?.[0] ?? 'Esta jogada não pôde ser validada. Volte para a mesa.', podeReenviar: false };
    }

    return { mensagem: 'Não foi possível confirmar seu tempo. Tente enviar novamente.', podeReenviar: true };
}
