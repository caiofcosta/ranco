<?php

declare(strict_types=1);

namespace App\Jogos;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Models\Jogo;

final class JogosDisponiveis
{
    /** @return list<array{id: string, nome: string, logo: string, minimo: int, maximo: int, ativo: bool}> */
    public function todos(): array
    {
        $ativos = array_fill_keys(Jogo::query()->where('ativo', true)->pluck('id')->all(), true);

        return array_map(
            static fn (array $jogo): array => $jogo + ['ativo' => isset($ativos[$jogo['id']])],
            CatalogoDeJogos::disponiveis(),
        );
    }

    /** @return list<array{id: string, nome: string, logo: string, minimo: int, maximo: int}> */
    public function disponiveis(): array
    {
        $disponiveis = [];
        foreach ($this->todos() as $jogo) {
            if (! $jogo['ativo']) {
                continue;
            }

            $disponiveis[] = [
                'id' => $jogo['id'],
                'nome' => $jogo['nome'],
                'logo' => $jogo['logo'],
                'minimo' => $jogo['minimo'],
                'maximo' => $jogo['maximo'],
            ];
        }

        return $disponiveis;
    }

    /** @return list<string> */
    public function idsDisponiveis(): array
    {
        return array_column($this->disponiveis(), 'id');
    }

    public function estaDisponivel(string $id): bool
    {
        return Jogo::query()->whereKey($id)->where('ativo', true)->exists();
    }
}
