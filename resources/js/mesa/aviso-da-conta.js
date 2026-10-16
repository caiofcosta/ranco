/**
 * Escuta o canal privado da própria conta. Quando a pessoa fica invisível em outra aba,
 * esta aba sai das presenças e recarrega; o servidor já não autoriza a presença dela.
 */
export function ouvirAvisosDaConta({ echo, pessoaId, aoFicarInvisivel }) {
    if (!pessoaId) return null;

    const canal = `App.Models.User.${pessoaId}`;
    echo.private(canal).listen('.pessoa.ficou-invisivel', aoFicarInvisivel);

    return canal;
}
