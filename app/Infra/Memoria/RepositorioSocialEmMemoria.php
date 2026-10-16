<?php

declare(strict_types=1);

namespace App\Infra\Memoria;

use App\Dominio\Social\RepositorioSocial;

final class RepositorioSocialEmMemoria implements RepositorioSocial
{
    /** @var array<string, array{0: string, 1: string}> */
    private array $pedidos = [];

    /** @var array<string, true> */
    private array $amizades = [];

    /** @var array<string, true> */
    private array $bloqueios = [];

    public function existePedido(string $de, string $para): bool
    {
        return isset($this->pedidos[self::direcao($de, $para)]);
    }

    public function registrarPedido(string $de, string $para): void
    {
        $this->pedidos[self::direcao($de, $para)] = [$de, $para];
    }

    public function removerPedido(string $de, string $para): void
    {
        unset($this->pedidos[self::direcao($de, $para)]);
    }

    public function pedidosRecebidos(string $usuario): array
    {
        $recebidos = [];
        foreach ($this->pedidos as [$de, $para]) {
            if ($para === $usuario) {
                $recebidos[] = $de;
            }
        }

        return $recebidos;
    }

    public function saoAmigos(string $a, string $b): bool
    {
        return isset($this->amizades[self::par($a, $b)]);
    }

    public function amigosDe(string $usuario): array
    {
        $amigos = [];
        foreach (array_keys($this->amizades) as $par) {
            [$a, $b] = explode("↔", (string) $par, 2);
            if ($a === $usuario) {
                $amigos[] = $b;
            } elseif ($b === $usuario) {
                $amigos[] = $a;
            }
        }

        return $amigos;
    }

    public function registrarAmizade(string $a, string $b): void
    {
        $this->amizades[self::par($a, $b)] = true;
    }

    public function removerAmizade(string $a, string $b): void
    {
        unset($this->amizades[self::par($a, $b)]);
    }

    public function bloqueou(string $quem, string $alvo): bool
    {
        return isset($this->bloqueios[self::direcao($quem, $alvo)]);
    }

    public function registrarBloqueio(string $quem, string $alvo): void
    {
        $this->bloqueios[self::direcao($quem, $alvo)] = true;
    }

    private static function direcao(string $de, string $para): string
    {
        return "{$de}→{$para}";
    }

    private static function par(string $a, string $b): string
    {
        return strcmp($a, $b) < 0 ? "{$a}↔{$b}" : "{$b}↔{$a}";
    }
}
