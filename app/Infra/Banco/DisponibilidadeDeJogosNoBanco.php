<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Jogos\RepositorioDeDisponibilidadeDeJogos;
use PDO;

final class DisponibilidadeDeJogosNoBanco implements RepositorioDeDisponibilidadeDeJogos
{
    public function __construct(private readonly PDO $pdo) {}

    public function estaDisponivel(string $jogo): bool
    {
        $consulta = $this->pdo->prepare('SELECT ativo FROM jogos WHERE id = :id');
        $consulta->execute(['id' => $jogo]);

        return (bool) $consulta->fetchColumn();
    }
}
