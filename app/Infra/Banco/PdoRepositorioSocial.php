<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use PDO;
use App\Dominio\Relogio;
use App\Dominio\Social\RepositorioSocial;

final class PdoRepositorioSocial implements RepositorioSocial
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Relogio $relogio,
    ) {
    }

    public function existePedido(string $de, string $para): bool
    {
        return $this->existe('SELECT 1 FROM pedidos_amizade WHERE de = ? AND para = ?', [$de, $para]);
    }

    public function registrarPedido(string $de, string $para): void
    {
        $this->executar('INSERT IGNORE INTO pedidos_amizade (de, para, criado_em) VALUES (?, ?, ?)', [$de, $para, $this->agora()]);
    }

    public function removerPedido(string $de, string $para): void
    {
        $this->executar('DELETE FROM pedidos_amizade WHERE de = ? AND para = ?', [$de, $para]);
    }

    public function pedidosRecebidos(string $usuario): array
    {
        $consulta = $this->pdo->prepare('SELECT de FROM pedidos_amizade WHERE para = ? ORDER BY criado_em');
        $consulta->execute([$usuario]);

        return array_map('strval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    }

    public function saoAmigos(string $a, string $b): bool
    {
        return $this->existe('SELECT 1 FROM amizades WHERE usuario_a = ? AND usuario_b = ?', self::par($a, $b));
    }

    public function amigosDe(string $usuario): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT usuario_b FROM amizades WHERE usuario_a = ? UNION SELECT usuario_a FROM amizades WHERE usuario_b = ?',
        );
        $consulta->execute([$usuario, $usuario]);

        return array_values(array_map('strval', $consulta->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function registrarAmizade(string $a, string $b): void
    {
        $this->executar(
            'INSERT IGNORE INTO amizades (usuario_a, usuario_b, criada_em) VALUES (?, ?, ?)',
            [...self::par($a, $b), $this->agora()],
        );
    }

    public function removerAmizade(string $a, string $b): void
    {
        $this->executar('DELETE FROM amizades WHERE usuario_a = ? AND usuario_b = ?', self::par($a, $b));
    }

    public function bloqueou(string $quem, string $alvo): bool
    {
        return $this->existe('SELECT 1 FROM bloqueios WHERE quem = ? AND alvo = ?', [$quem, $alvo]);
    }

    public function registrarBloqueio(string $quem, string $alvo): void
    {
        $this->executar('INSERT IGNORE INTO bloqueios (quem, alvo, criado_em) VALUES (?, ?, ?)', [$quem, $alvo, $this->agora()]);
    }

    /** @return array{0: string, 1: string} O par na ordem em que é gravado. */
    private static function par(string $a, string $b): array
    {
        return strcmp($a, $b) < 0 ? [$a, $b] : [$b, $a];
    }

    /** @param list<string> $parametros */
    private function existe(string $sql, array $parametros): bool
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);

        return $consulta->fetchColumn() !== false;
    }

    /** @param list<string> $parametros */
    private function executar(string $sql, array $parametros): void
    {
        $this->pdo->prepare($sql)->execute($parametros);
    }

    private function agora(): string
    {
        return $this->relogio->agora()->format(FormatoDeData::PADRAO);
    }
}
