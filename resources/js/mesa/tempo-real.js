import '../echo';
import { criarTempoReal } from './ciclo-tempo-real.js';
import { criarPresencaDeConvidado } from './presenca-de-convidado.js';
import { ouvirAvisosDaConta } from './aviso-da-conta.js';

const tempoReal = criarTempoReal({
    documento: document,
    echo: window.Echo,
    avisarMesa: (mesaId) => window.Livewire?.dispatch(`mesa-atualizada.${mesaId}`),
    conexao: window.Echo.connector?.pusher?.connection ?? null,
});
const convidado = criarPresencaDeConvidado({ documento: document, echo: window.Echo });

const sincronizar = () => {
    tempoReal.sincronizar();
    convidado.sincronizar();
};

sincronizar();
document.addEventListener('livewire:navigated', sincronizar);

ouvirAvisosDaConta({
    echo: window.Echo,
    pessoaId: document.querySelector('meta[name="pessoa-id"]')?.content,
    aoFicarInvisivel: () => {
        convidado.sairDeTodas();
        tempoReal.encerrar();
        window.location.reload();
    },
});

// Cada redesenho da tela da mesa apaga o data-online das cadeiras; reacende com a presença atual.
const ligarAoLivewire = () => window.Livewire.hook('morphed', () => tempoReal.reacender());
if (window.Livewire) ligarAoLivewire();
else document.addEventListener('livewire:init', ligarAoLivewire, { once: true });

// Também libera presença ao navegar para fora do jogo ou fechar a página.
document.addEventListener('livewire:navigating', () => tempoReal.encerrar());
window.addEventListener('pagehide', () => { tempoReal.encerrar(); convidado.sairDeTodas(); });
window.addEventListener('pageshow', (evento) => { if (evento.persisted) window.location.reload(); });
