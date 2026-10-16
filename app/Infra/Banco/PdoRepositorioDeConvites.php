<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use PDO;
use App\Dominio\Plataforma\RepositorioDeConvites;
use App\Dominio\Relogio;
use App\Dominio\Social\Convite;
use App\Dominio\Social\SituacaoConvite;

final class PdoRepositorioDeConvites implements RepositorioDeConvites
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Relogio $relogio,
    ) {
    }

    public function novoId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function buscar(string $id): ?Convite
    {
        return $this->consultar('SELECT * FROM convites WHERE id = ?', [$id])[0] ?? null;
    }

    public function salvar(Convite $convite): void
    {
        $this->pdo->prepare(
            'INSERT INTO convites (id, mesa_id, de, para, situacao, criado_em) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE situacao = VALUES(situacao)',
        )->execute([
            $convite->id,
            $convite->mesaId,
            $convite->de,
            $convite->para,
            $convite->situacao->value,
            $this->relogio->agora()->format(FormatoDeData::PADRAO),
        ]);
    }

    public function pendentesDaMesa(string $mesaId): array
    {
        return $this->consultar(
            'SELECT * FROM convites WHERE mesa_id = ? AND situacao = ? ORDER BY criado_em',
            [$mesaId, SituacaoConvite::Pendente->value],
        );
    }

    public function pendentesEntre(string $a, string $b): array
    {
        return $this->consultar(
            'SELECT * FROM convites WHERE situacao = ? AND ((de = ? AND para = ?) OR (de = ? AND para = ?)) ORDER BY criado_em',
            [SituacaoConvite::Pendente->value, $a, $b, $b, $a],
        );
    }

    public function pendentesPara(string $usuario): array
    {
        return $this->consultar(
            'SELECT * FROM convites WHERE para = ? AND situacao = ? ORDER BY criado_em',
            [$usuario, SituacaoConvite::Pendente->value],
        );
    }

    /**
     * @param list<string> $parametros
     * @return list<Convite>
     */
    private function consultar(string $sql, array $parametros): array
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);

        return array_map(
            static fn (array $linha): Convite => Convite::restaurar(
                (string) $linha['id'],
                (string) $linha['mesa_id'],
                (string) $linha['de'],
                (string) $linha['para'],
                SituacaoConvite::from((string) $linha['situacao']),
            ),
            $consulta->fetchAll(),
        );
    }
}
