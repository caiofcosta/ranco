<?php

namespace App\Servicos;

use App\Models\ModalDeNotificacao;
use App\Models\DismissaoDeModal;
use App\Models\User;

class RepositorioDeModaisDeNotificacao
{
    /**
     * Obtém a modal ativa para um usuário no mobile (onboarding ou outro tipo)
     */
    public function obterModalParaUsuario(User $user, string $tipo = 'onboarding'): ?ModalDeNotificacao
    {
        $modal = ModalDeNotificacao::ativas()
            ->porTipo($tipo)
            ->first();

        if (!$modal) {
            return null;
        }

        // Verificar se o usuário já descartou essa modal
        $dismissao = DismissaoDeModal::where('user_id', $user->id)
            ->where('modal_de_notificacao_id', $modal->id)
            ->first();

        if ($dismissao && $dismissao->nao_mostrar_novamente) {
            return null;
        }

        return $modal;
    }

    /**
     * Registrar que o usuário visualizou a modal
     */
    public function marcarComoVisualizada(User $user, ModalDeNotificacao $modal): void
    {
        DismissaoDeModal::updateOrCreate(
            [
                'user_id' => $user->id,
                'modal_de_notificacao_id' => $modal->id,
            ],
            [
                'visualizou_em' => now(),
            ]
        );
    }

    /**
     * Registrar que o usuário descartou a modal
     */
    public function marcarComoDescartada(User $user, ModalDeNotificacao $modal, bool $naoMostrarNovamente = false): void
    {
        DismissaoDeModal::updateOrCreate(
            [
                'user_id' => $user->id,
                'modal_de_notificacao_id' => $modal->id,
            ],
            [
                'descartou_em' => now(),
                'nao_mostrar_novamente' => $naoMostrarNovamente,
            ]
        );
    }
}
