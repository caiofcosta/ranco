<?php

declare(strict_types=1);

namespace App\Mesa;

/**
 * Nomes já lidos nesta requisição, por mesa. Não é estático: outra requisição
 * começa vazia, então um nome trocado no meio do caminho não fica preso.
 */
final class NomesDaMesaNaRequisicao
{
    /** @var array<string, array<string, ?string>> */
    private array $porMesa = [];

    private ?object $requisicao = null;

    /** @return array<string, ?string>|null */
    public function lembrar(string $mesaId): ?array
    {
        $this->alinharComARequisicao();

        return $this->porMesa[$mesaId] ?? null;
    }

    /** @param array<string, ?string> $nomes */
    public function guardar(string $mesaId, array $nomes): void
    {
        $this->alinharComARequisicao();
        $this->porMesa[$mesaId] = $nomes;
    }

    private function alinharComARequisicao(): void
    {
        $atual = app()->bound('request') ? request() : null;
        if ($atual !== $this->requisicao) {
            $this->porMesa = [];
            $this->requisicao = $atual;
        }
    }
}
