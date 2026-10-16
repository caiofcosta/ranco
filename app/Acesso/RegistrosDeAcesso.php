<?php

declare(strict_types=1);

namespace App\Acesso;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Registros privados: cada login e, durante o uso, uma ocorrência por conta/IP a cada hora. */
final class RegistrosDeAcesso
{
    public function registrar(User $pessoa, Request $pedido, bool $entrada = false): void
    {
        // Request::ip respeita a configuração de proxies confiáveis do Laravel.
        $ip = $pedido->ip();
        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return;
        }
        $chave = 'registro-de-acesso:'.hash('sha256', $pessoa->id.'|'.$ip);
        if (! $entrada && Cache::has($chave)) {
            return;
        }

        $agora = CarbonImmutable::now('UTC');
        $ate = DB::transaction(function () use ($pessoa, $ip, $pedido, $entrada, $agora): ?CarbonImmutable {
            if (User::query()->whereKey($pessoa->id)->lockForUpdate()->first() === null) {
                return null;
            }
            if (! $entrada) {
                $ultimo = DB::table('registros_de_acesso')->where('user_id', $pessoa->id)->where('ip', $ip)
                    ->orderByDesc('ocorrido_em')->lockForUpdate()->value('ocorrido_em');
                if ($ultimo !== null) {
                    $ate = CarbonImmutable::parse($ultimo, 'UTC')->addHour();
                    if ($ate->greaterThan($agora)) {
                        return $ate;
                    }
                }
            }

            DB::table('registros_de_acesso')->insert([
                'user_id' => $pessoa->id,
                'ip' => $ip,
                'porta_origem' => $this->portaDaConexaoDireta($pedido, $ip),
                'ocorrido_em' => $agora->format('Y-m-d H:i:s.v'),
            ]);

            return $agora->addHour();
        });

        if ($ate !== null) {
            Cache::put($chave, true, $ate);
        }
    }

    public function limparExpirados(): int
    {
        $limite = CarbonImmutable::now('UTC')->subMonthsNoOverflow(6)->format('Y-m-d H:i:s.v');
        $removidos = 0;
        do {
            $ids = DB::table('registros_de_acesso')->where('ocorrido_em', '<', $limite)
                ->orderBy('ocorrido_em')->orderBy('id')->limit(1000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $removidos += DB::table('registros_de_acesso')->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return $removidos;
    }

    private function portaDaConexaoDireta(Request $pedido, string $ip): ?int
    {
        // REMOTE_PORT atrás de proxy é a porta do proxy. X-Forwarded-Port é destino,
        // não origem. Sem uma informação confiável da origem, permanece null.
        if ($pedido->server('REMOTE_ADDR') !== $ip) {
            return null;
        }
        $porta = filter_var($pedido->server('REMOTE_PORT'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);

        return $porta === false ? null : $porta;
    }
}
