<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use PDO;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocacao;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;

final class PdoRepositorioDeProvocacoes implements RepositorioDeProvocacoes
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function candidatasPara(Momento $momento): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT p.id, p.texto, p.intensidade, p.perspectiva, GROUP_CONCAT(pt.tema ORDER BY pt.tema) AS temas
             FROM provocacoes p
             JOIN provocacoes_temas pt ON pt.provocacao_id = p.id
             WHERE p.ativa = 1
               AND p.id IN (
                   SELECT aceita.provocacao_id
                   FROM provocacoes_temas aceita
                   JOIN momentos_temas mt ON mt.tema = aceita.tema
                   WHERE mt.momento = ?
               )
             GROUP BY p.id, p.texto, p.intensidade, p.perspectiva',
        );
        $consulta->execute([$momento->value]);

        return array_map(
            static fn (array $linha): Provocacao => new Provocacao(
                (int) $linha['id'],
                (string) $linha['texto'],
                (int) $linha['intensidade'],
                explode(',', (string) $linha['temas']),
                Perspectiva::from((string) $linha['perspectiva']),
            ),
            $consulta->fetchAll(),
        );
    }
}
