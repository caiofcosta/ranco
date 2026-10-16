<?php

declare(strict_types=1);

namespace App\Amizade;

use App\Convite\ConviteRecebido;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\ConviteDeAmizade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Convites entre contas. O endereço informado é usado apenas para localizar a conta. */
final class ConvitesDeAmizade
{
    public const DIAS_DE_VALIDADE = 7;

    public const MENSAGEM_ENVIO = 'Se houver uma conta com esse e-mail, a pessoa vai ver seu convite.';

    public function __construct(private readonly Plataforma $plataforma) {}

    public function enviar(User $quemConvida, string $email): void
    {
        $destinatario = DB::transaction(function () use ($quemConvida, $email): ?string {
            // Serializa envios do mesmo remetente para não duplicar convites pendentes.
            User::query()->whereKey($quemConvida->id)->lockForUpdate()->firstOrFail();
            $de = $quemConvida->identificadorNoDominio();
            if ($this->plataforma->estaInvisivel($de)) {
                throw new RegraViolada('Fique visível para interagir com outras pessoas.');
            }

            $convidada = User::query()->where('email', mb_strtolower(trim($email)))->first();
            if ($convidada === null || $convidada->id === $quemConvida->id) {
                return null;
            }
            $para = $convidada->identificadorNoDominio();
            if ($this->plataforma->saoAmigos($de, $para)
                || $this->plataforma->bloqueou($para, $de)
                || $this->plataforma->bloqueou($de, $para)) {
                return null;
            }
            if (ConviteDeAmizade::query()->where('de', $de)->where('para', $convidada->id)
                ->whereNull('aceito_em')->whereNull('recusado_em')->where('expira_em', '>', now())->exists()) {
                return null;
            }

            ConviteDeAmizade::create([
                'de' => $de,
                'para' => $convidada->id,
                'expira_em' => now()->addDays(self::DIAS_DE_VALIDADE),
            ]);

            return $para;
        });

        if ($destinatario !== null) {
            ConviteRecebido::dispatch($destinatario);
        }
    }

    public function aceitar(ConviteDeAmizade $convite, User $quem): void
    {
        DB::transaction(function () use ($convite, $quem): void {
            $atual = ConviteDeAmizade::query()->lockForUpdate()->findOrFail($convite->id);
            if (! $this->foiPara($atual, $quem) || ! $atual->aberto()) {
                throw new RegraViolada('Este convite venceu ou já foi usado.');
            }
            $this->plataforma->aceitarConviteDeAmizade($atual->de, $quem->identificadorNoDominio());
            $atual->forceFill(['aceito_em' => now(), 'aceito_por' => $quem->identificadorNoDominio()])->save();
        });
    }

    public function recusar(ConviteDeAmizade $convite, User $quem): void
    {
        DB::transaction(function () use ($convite, $quem): void {
            $atual = ConviteDeAmizade::query()->lockForUpdate()->findOrFail($convite->id);
            if (! $this->foiPara($atual, $quem) || ! $atual->aberto()) {
                throw new RegraViolada('Este convite não está mais disponível.');
            }
            $atual->forceFill(['recusado_em' => now()])->save();
        });
    }

    public function foiPara(ConviteDeAmizade $convite, User $quem): bool
    {
        return $convite->para === $quem->id;
    }

    /** @return Collection<int, ConviteDeAmizade> */
    public function recebidos(User $quem): Collection
    {
        $eu = $quem->identificadorNoDominio();

        return ConviteDeAmizade::query()->where('para', $quem->id)
            ->whereNull('aceito_em')->whereNull('recusado_em')->where('expira_em', '>', now())
            ->where('de', '!=', $eu)->latest()->get()
            ->reject(fn (ConviteDeAmizade $convite): bool => $this->plataforma->bloqueou($eu, $convite->de)
                || $this->plataforma->saoAmigos($eu, $convite->de))
            ->values();
    }
}
