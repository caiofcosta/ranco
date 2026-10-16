/**
 * Na Início de quem recebeu convite: entra na presença das mesas dos convites pendentes,
 * para o admin ver a cadeira do convidado acender. Sai quando o convite deixa a tela.
 */
export function criarPresencaDeConvidado({ documento, echo }) {
    let mesas = new Set();

    function sincronizar() {
        const atuais = new Set(
            [...documento.querySelectorAll('[data-presenca-convite]')]
                .map((convite) => convite.dataset.presencaConvite)
                .filter(Boolean),
        );

        for (const mesaId of mesas) {
            if (!atuais.has(mesaId)) echo.leave(`presenca.mesa.${mesaId}`);
        }
        for (const mesaId of atuais) {
            if (!mesas.has(mesaId)) echo.join(`presenca.mesa.${mesaId}`);
        }
        mesas = atuais;
    }

    function sairDeTodas() {
        for (const mesaId of mesas) echo.leave(`presenca.mesa.${mesaId}`);
        mesas = new Set();
    }

    return { sincronizar, sairDeTodas };
}
