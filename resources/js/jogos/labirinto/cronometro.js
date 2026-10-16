export class Cronometro {
    inicio = null;
    fim = null;

    iniciar(agora) {
        if (this.inicio === null) this.inicio = agora;
    }

    concluir(agora) {
        if (this.inicio !== null && this.fim === null) this.fim = agora;
        return this.tempo(agora);
    }

    tempo(agora) {
        return this.inicio === null ? 0 : Math.max(0, Math.round((this.fim ?? agora) - this.inicio));
    }
}

export function formatarTempo(tempo) {
    return `${(tempo / 1000).toFixed(3).replace('.', ',')} s`;
}
