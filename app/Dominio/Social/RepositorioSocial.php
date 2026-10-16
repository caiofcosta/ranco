<?php

declare(strict_types=1);

namespace App\Dominio\Social;

interface RepositorioSocial
{
    public function existePedido(string $de, string $para): bool;

    public function registrarPedido(string $de, string $para): void;

    public function removerPedido(string $de, string $para): void;

    /** @return list<string> Quem pediu amizade ao usuário e ainda aguarda resposta. */
    public function pedidosRecebidos(string $usuario): array;

    public function saoAmigos(string $a, string $b): bool;

    /** @return list<string> Com quem o usuário tem amizade aceita. */
    public function amigosDe(string $usuario): array;

    public function registrarAmizade(string $a, string $b): void;

    public function removerAmizade(string $a, string $b): void;

    public function bloqueou(string $quem, string $alvo): bool;

    public function registrarBloqueio(string $quem, string $alvo): void;
}
