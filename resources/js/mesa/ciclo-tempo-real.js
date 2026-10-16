import { incluirPresenca, removerPresenca } from './presenca.js';

/**
 * Liga a página atual aos canais da mesa e desliga ao sair dela.
 * Com wire:navigate o módulo roda uma vez só; por isso a ligação é refeita a cada navegação.
 */
export function criarTempoReal({ documento, echo, avisarMesa, conexao = null }) {
    let atual = null;
    let conectouAntes = false;

    function encerrar() {
        if (!atual) return;
        echo.leave(`mesa.${atual.mesaId}`);
        echo.leave(`presenca.mesa.${atual.mesaId}`);
        atual = null;
    }

    function sincronizar() {
        const lista = documento.querySelector('[data-tempo-real-presenca]');
        const mesaId = documento.querySelector('[data-tempo-real-mesa]')?.dataset.mesaId;
        if (!lista || !mesaId) {
            encerrar();
            return;
        }
        if (atual && atual.lista === lista && atual.mesaId === mesaId) return;

        encerrar();
        const estado = { mesaId, lista, presentes: [] };
        atual = estado;

        const mostrar = (presentes) => {
            if (atual !== estado) return;
            estado.presentes = presentes;
            estado.lista.replaceChildren(...presentes.map((membro) => {
                const item = documento.createElement('li');
                item.textContent = membro.nome;
                return item;
            }));
            acenderCadeiras(presentes);
        };

        echo.private(`mesa.${mesaId}`).listen('.mesa.mudou', () => {
            if (atual === estado) avisarMesa(mesaId);
        });
        echo.join(`presenca.mesa.${mesaId}`)
            .here((membros) => mostrar(membros))
            .joining((membro) => mostrar(incluirPresenca(estado.presentes, membro)))
            .leaving((membro) => mostrar(removerPresenca(estado.presentes, membro.id)));
    }

    // Cadeiras da mesa acendem para quem está online.
    function acenderCadeiras(presentes) {
        const online = new Set(presentes.map((membro) => String(membro.id)));
        documento.querySelectorAll?.('[data-cadeira]').forEach((cadeira) => {
            cadeira.toggleAttribute('data-online', online.has(cadeira.dataset.cadeira));
        });
    }

    // O redesenho do Livewire tira o data-online das cadeiras; a última presença conhecida volta a acendê-las.
    function reacender() {
        if (atual) acenderCadeiras(atual.presentes);
    }

    // Depois de uma queda, o sinal perdido é compensado relendo a mesa na reconexão.
    conexao?.bind('connected', () => {
        if (conectouAntes && atual) avisarMesa(atual.mesaId);
        conectouAntes = true;
    });

    return { sincronizar, encerrar, reacender };
}
