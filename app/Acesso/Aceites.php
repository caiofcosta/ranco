<?php

declare(strict_types=1);

namespace App\Acesso;

use App\Models\Aceite;
use App\Models\User;

final class Aceites
{
    public const VERSAO_VIGENTE = '2026-10-05';

    public function __construct(private readonly string $versao = self::VERSAO_VIGENTE) {}

    public function versaoVigente(): string
    {
        return $this->versao;
    }

    public function aceitouVigente(User $pessoa): bool
    {
        return $pessoa->aceites()->where('versao', $this->versaoVigente())->exists();
    }

    public function registrar(User $pessoa, string $ip): void
    {
        // A unicidade protege envios simultâneos e preserva a data e o IP iniciais.
        Aceite::query()->firstOrCreate([
            'user_id' => $pessoa->id,
            'versao' => $this->versaoVigente(),
        ], [
            'aceito_em' => now('UTC'),
            'ip' => $ip,
        ]);
    }
}
