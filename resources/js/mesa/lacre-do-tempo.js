/** Mesma conta de App\Mesa\LacreDoTempo (FNV-1a de 32 bits): o servidor recusa tempo trocado no envio. */
export function lacrarTempo(jogada, tempo) {
    let valor = 0x811c9dc5;
    for (const byte of new TextEncoder().encode(`ranco|${tempo}|${jogada}`)) {
        valor = Math.imul(valor ^ byte, 0x01000193);
    }
    return (valor >>> 0).toString(16).padStart(8, '0');
}
