/**
 * Gerencia a modal de notificações para mobile
 * Comunica com o servidor para registrar visualizações e dismissões
 */

export function inicializarOnboarding() {
    const modal = document.querySelector('[data-onboarding-modal]');
    if (!modal) return;

    const modalId = modal.dataset.modalId;
    const botao = modal.querySelector('[data-onboarding-botao]');
    const fechar = modal.querySelector('[data-onboarding-fechar]');
    const checkbox = modal.querySelector('[data-onboarding-nao-mostrar]');

    // Mostrar a modal
    mostrarModal(modal);

    // Registrar visualização
    registrarVisualizacao(modalId);

    // Listeners para fechar
    botao.addEventListener('click', () => fecharModal(modal, modalId, checkbox.checked));
    fechar.addEventListener('click', () => fecharModal(modal, modalId, checkbox.checked));

    // Fechar ao clicar no overlay (background)
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            fecharModal(modal, modalId, checkbox.checked);
        }
    });

    // Fechar com Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('opacity-100')) {
            fecharModal(modal, modalId, checkbox.checked);
        }
    });
}

function mostrarModal(modal) {
    // Pequeno delay para garantir que a transição seja visível
    setTimeout(() => {
        modal.classList.remove('opacity-0', 'invisible');
        modal.classList.add('opacity-100', 'visible');
        modal.style.pointerEvents = 'auto';
    }, 50);
}

function fecharModal(modal, modalId, naoMostrarNovamente) {
    modal.classList.add('opacity-0', 'invisible');
    modal.classList.remove('opacity-100', 'visible');

    // Registrar dismissão no servidor
    registrarDismissao(modalId, naoMostrarNovamente);

    // Após a transição, remover a modal do DOM (opcional, só para limpeza)
    setTimeout(() => {
        modal.style.pointerEvents = 'none';
    }, 300);
}

function registrarVisualizacao(modalId) {
    fetch('/api/modal-notificacao/visualizar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ modal_id: modalId }),
    }).catch(err => console.error('Erro ao registrar visualização:', err));
}

function registrarDismissao(modalId, naoMostrarNovamente) {
    fetch('/api/modal-notificacao/dismissar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({
            modal_id: modalId,
            nao_mostrar_novamente: naoMostrarNovamente,
        }),
    }).catch(err => console.error('Erro ao registrar dismissão:', err));
}

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarOnboarding);
} else {
    inicializarOnboarding();
}
