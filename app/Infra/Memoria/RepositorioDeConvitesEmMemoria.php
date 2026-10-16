<?php

declare(strict_types=1);

namespace App\Infra\Memoria;

use App\Dominio\Plataforma\RepositorioDeConvites;
use App\Dominio\Social\Convite;

final class RepositorioDeConvitesEmMemoria implements RepositorioDeConvites
{
    /** @var array<string, Convite> */
    private array $convites = [];

    private int $sequencia = 0;

    public function novoId(): string
    {
        return 'convite-' . ++$this->sequencia;
    }

    public function buscar(string $id): ?Convite
    {
        return $this->convites[$id] ?? null;
    }

    public function salvar(Convite $convite): void
    {
        $this->convites[$convite->id] = $convite;
    }

    public function pendentesDaMesa(string $mesaId): array
    {
        return $this->pendentes(static fn (Convite $c): bool => $c->mesaId === $mesaId);
    }

    public function pendentesEntre(string $a, string $b): array
    {
        return $this->pendentes(static fn (Convite $c): bool => $c->envolve($a, $b));
    }

    public function pendentesPara(string $usuario): array
    {
        return $this->pendentes(static fn (Convite $c): bool => $c->para === $usuario);
    }

    /**
     * @param callable(Convite): bool $filtro
     * @return list<Convite>
     */
    private function pendentes(callable $filtro): array
    {
        return array_values(array_filter($this->convites, static fn (Convite $c): bool => $c->pendente() && $filtro($c)));
    }
}
