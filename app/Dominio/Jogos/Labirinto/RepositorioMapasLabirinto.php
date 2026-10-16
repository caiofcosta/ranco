<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use DateTimeImmutable;
use stdClass;

/** @phpstan-import-type Mapa from CatalogoDeMapasLabirinto */
interface RepositorioMapasLabirinto
{
    /** @return list<Mapa> */
    public function listar(): array;

    /** @return Mapa */
    public function buscar(int $id): array;

    /** @return Mapa */
    public function bloquear(int $id): array;

    /** @return list<stdClass> */
    public function versoes(int $id): array;

    /** @return array{id: int, nome: string, tipo: string}|null */
    public function conflito(?int $id, string $chaveNome, string $hash): ?array;

    /** @param Mapa $mapa */
    public function gravar(?int $id, array $mapa, string $chaveNome, DateTimeImmutable $agora): int;

    public function reservar(int $id, string $hash): void;

    /** @param array{formato: int, linhas: list<string>, tamanho_celula: int, raio_bolinha: int, velocidade: int} $definicao */
    public function publicar(int $id, int $numero, string $nome, array $definicao, ?int $autor, DateTimeImmutable $agora): void;

    /** @param list<string> $hashes */
    public function liberarRascunhos(int $id, array $hashes): void;
}
