<?php

declare(strict_types=1);

namespace App\Painel;

use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdministrarUsuario
{
    public function bloquear(User $administrador, int $usuarioId): void
    {
        DB::transaction(function () use ($administrador, $usuarioId): void {
            $usuario = User::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $this->autorizarAlvo($administrador, $usuario);

            $usuario->forceFill([
                'bloqueado_em' => $usuario->bloqueado_em ?? now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->invalidarSessoes($usuario);
        });
    }

    public function desbloquear(User $administrador, int $usuarioId): void
    {
        DB::transaction(function () use ($administrador, $usuarioId): void {
            $usuario = User::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $this->autorizarAlvo($administrador, $usuario);

            if ($usuario->bloqueado_em === null) {
                return;
            }

            $usuario->forceFill(['bloqueado_em' => null])->save();
        });
    }

    public function excluir(User $administrador, int $usuarioId): void
    {
        DB::transaction(function () use ($administrador, $usuarioId): void {
            $usuario = User::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $this->autorizarAlvo($administrador, $usuario);
            $id = $usuario->identificadorNoDominio();

            $this->encerrarMesasDoAdmin($id);
            $this->retirarDeMesasCompartilhadas($id);
            $this->apagarRelacoesDaConta($usuario);
            $this->invalidarSessoes($usuario);

            $usuario->delete();
        });
    }

    private function autorizarAlvo(User $administrador, User $usuario): void
    {
        $administradores = array_map('mb_strtolower', config('ranco.administradores', []));

        if ($administrador->is($usuario) || in_array(mb_strtolower($usuario->email), $administradores, true)) {
            throw new AuthorizationException('Essa conta não pode ser bloqueada nem excluída pelo Painel.');
        }
    }

    private function encerrarMesasDoAdmin(string $id): void
    {
        $ids = DB::table('mesas')
            ->where('admin', $id)
            ->where('situacao', 'aberta')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $mesaId) {
            app(Plataforma::class)->encerrarMesa((string) $mesaId, $id);
        }
    }

    private function retirarDeMesasCompartilhadas(string $id): void
    {
        $ids = DB::table('membros_mesa')
            ->join('mesas', 'mesas.id', '=', 'membros_mesa.mesa_id')
            ->where('membros_mesa.usuario', $id)
            ->where('membros_mesa.situacao', 'ativa')
            ->where('mesas.situacao', 'aberta')
            ->where('mesas.admin', '!=', $id)
            ->orderBy('mesas.id')
            ->pluck('mesas.id');

        foreach ($ids as $mesaId) {
            app(Plataforma::class)->abandonarMesa((string) $mesaId, $id);
        }
    }

    private function apagarRelacoesDaConta(User $usuario): void
    {
        $id = $usuario->identificadorNoDominio();
        DB::table('amizades')->where('usuario_a', $id)->orWhere('usuario_b', $id)->delete();
        DB::table('pedidos_amizade')->where('de', $id)->orWhere('para', $id)->delete();
        DB::table('bloqueios')->where('quem', $id)->orWhere('alvo', $id)->delete();
        DB::table('convites_de_amizade')->where('de', $id)->orWhere('para', $usuario->id)->delete();
        DB::table('convites')->where('de', $id)->orWhere('para', $id)->delete();
        DB::table('participantes_locais')->where('usuario', $id)->update(['usuario' => null]);
        DB::table('revelacoes_vistas')->where('usuario', $id)->delete();
        DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();
    }

    private function invalidarSessoes(User $usuario): void
    {
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();
    }
}
