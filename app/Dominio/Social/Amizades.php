<?php

declare(strict_types=1);

namespace App\Dominio\Social;

use App\Dominio\RegraViolada;

final class Amizades
{
    public function __construct(private readonly RepositorioSocial $repositorio)
    {
    }

    public function solicitar(string $de, string $para): void
    {
        if ($de === $para) {
            throw new RegraViolada('Não é possível pedir amizade a si mesmo.');
        }
        if ($this->repositorio->bloqueou($para, $de)) {
            throw new RegraViolada('Pedido não permitido: a pessoa bloqueou você.');
        }
        if ($this->repositorio->saoAmigos($de, $para)) {
            throw new RegraViolada('Vocês já são amigos.');
        }

        $this->repositorio->registrarPedido($de, $para);
    }

    public function aceitar(string $quem, string $de): void
    {
        if (!$this->repositorio->existePedido($de, $quem)) {
            throw new RegraViolada('Não há pedido de amizade pendente.');
        }

        $this->repositorio->removerPedido($de, $quem);
        $this->repositorio->registrarAmizade($de, $quem);
    }

    public function desfazer(string $quem, string $outro): void
    {
        if (!$this->repositorio->saoAmigos($quem, $outro)) {
            throw new RegraViolada('Vocês não são amigos.');
        }

        $this->repositorio->removerAmizade($quem, $outro);
    }

    public function bloquear(string $quem, string $alvo): void
    {
        if ($quem === $alvo) {
            throw new RegraViolada('Não é possível bloquear a si mesmo.');
        }

        $this->repositorio->registrarBloqueio($quem, $alvo);
        $this->repositorio->removerPedido($quem, $alvo);
        $this->repositorio->removerPedido($alvo, $quem);
    }

    public function saoAmigos(string $a, string $b): bool
    {
        return $this->repositorio->saoAmigos($a, $b);
    }

    /** @return list<string> */
    public function amigosDe(string $usuario): array
    {
        return $this->repositorio->amigosDe($usuario);
    }

    public function bloqueou(string $quem, string $alvo): bool
    {
        return $this->repositorio->bloqueou($quem, $alvo);
    }

    /** @return list<string> */
    public function pedidosRecebidos(string $usuario): array
    {
        return $this->repositorio->pedidosRecebidos($usuario);
    }
}
