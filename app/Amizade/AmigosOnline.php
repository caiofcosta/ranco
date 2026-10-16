<?php

declare(strict_types=1);

namespace App\Amizade;

use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Quem dos amigos está com o RANÇO aberto. "Online" é atividade nos últimos 2 minutos;
 * a página aberta avisa o servidor a cada 45 segundos. Cada pessoa só vê os próprios amigos.
 */
final class AmigosOnline
{
    public const JANELA_SEGUNDOS = 120;

    public function __construct(private readonly Plataforma $plataforma) {}

    /** Marca a conta como ativa agora, sem mexer em `updated_at`. */
    public function registrar(User $usuario, DateTimeImmutable $agora): void
    {
        DB::table('users')->where('id', $usuario->id)->update(['visto_em' => self::emUtc($agora)]);
    }

    /**
     * @return array{online: int, offline: int, pessoas: list<array{id: string, nome: string, foto: ?string, online: bool, visto: string}>}
     */
    public function de(User $usuario, DateTimeImmutable $agora): array
    {
        $amigos = $this->plataforma->amigosDe($usuario->identificadorNoDominio());
        if ($amigos === []) {
            return ['online' => 0, 'offline' => 0, 'pessoas' => []];
        }

        $limite = $agora->modify('-'.self::JANELA_SEGUNDOS.' seconds');
        $pessoas = [];
        foreach (DB::table('users')->whereIn('id', $amigos)->get(['id', 'name', 'google_avatar_url', 'visto_em', 'invisivel']) as $linha) {
            $visto = $linha->visto_em === null ? null : new DateTimeImmutable($linha->visto_em, new DateTimeZone('UTC'));
            $online = ! $linha->invisivel && $visto !== null && $visto >= $limite;
            $pessoas[] = [
                'id' => (string) $linha->id,
                'nome' => (string) $linha->name,
                'foto' => $linha->google_avatar_url,
                'online' => $online,
                'visto' => $linha->invisivel ? '' : ($online ? 'online agora' : self::vistoHa($visto, $agora)),
                'ordem' => $linha->invisivel ? 0 : ($visto?->getTimestamp() ?? 0),
            ];
        }

        // Online primeiro; depois quem foi visto mais recentemente; empate pelo nome.
        usort($pessoas, static fn (array $a, array $b): int => [$b['online'], $b['ordem'], $a['nome']] <=> [$a['online'], $a['ordem'], $b['nome']]);
        $online = count(array_filter($pessoas, static fn (array $pessoa): bool => $pessoa['online']));

        return [
            'online' => $online,
            'offline' => count($pessoas) - $online,
            'pessoas' => array_map(static fn (array $pessoa): array => [
                'id' => $pessoa['id'],
                'nome' => $pessoa['nome'],
                'foto' => $pessoa['foto'],
                'online' => $pessoa['online'],
                'visto' => $pessoa['visto'],
            ], $pessoas),
        ];
    }

    public static function vistoHa(?DateTimeImmutable $visto, DateTimeImmutable $agora): string
    {
        if ($visto === null) {
            return 'ainda não apareceu';
        }

        $segundos = max(0, $agora->getTimestamp() - $visto->getTimestamp());

        return match (true) {
            $segundos < 3600 => 'visto há '.max(1, intdiv($segundos, 60)).' min',
            $segundos < 86400 => 'visto há '.intdiv($segundos, 3600).' h',
            intdiv($segundos, 86400) === 1 => 'visto ontem',
            default => 'visto há '.intdiv($segundos, 86400).' dias',
        };
    }

    private static function emUtc(DateTimeImmutable $instante): string
    {
        return $instante->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
