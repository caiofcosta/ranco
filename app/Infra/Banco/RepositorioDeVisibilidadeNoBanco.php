<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Plataforma\RepositorioDeVisibilidade;
use PDO;

final class RepositorioDeVisibilidadeNoBanco implements RepositorioDeVisibilidade
{
    public function __construct(private readonly PDO $pdo) {}

    public function invisivel(string $pessoa): bool
    {
        $consulta = $this->pdo->prepare('SELECT invisivel FROM users WHERE id = ?');
        $consulta->execute([$pessoa]);

        return (bool) $consulta->fetchColumn();
    }

    public function definir(string $pessoa, bool $invisivel): void
    {
        $this->pdo->prepare('UPDATE users SET invisivel = ? WHERE id = ?')->execute([(int) $invisivel, $pessoa]);
    }
}
