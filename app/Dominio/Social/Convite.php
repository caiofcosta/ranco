<?php

declare(strict_types=1);

namespace App\Dominio\Social;

use App\Dominio\RegraViolada;

/** Convite de mesa: enviado pelo admin; não expira sozinho. */
final class Convite
{
    public private(set) SituacaoConvite $situacao = SituacaoConvite::Pendente;

    public function __construct(
        public readonly string $id,
        public readonly string $mesaId,
        public readonly string $de,
        public readonly string $para,
    ) {
    }

    public static function restaurar(string $id, string $mesaId, string $de, string $para, SituacaoConvite $situacao): self
    {
        $convite = new self($id, $mesaId, $de, $para);
        $convite->situacao = $situacao;

        return $convite;
    }

    public function pendente(): bool
    {
        return $this->situacao === SituacaoConvite::Pendente;
    }

    public function envolve(string $a, string $b): bool
    {
        return ($this->de === $a && $this->para === $b) || ($this->de === $b && $this->para === $a);
    }

    public function aceitar(): void
    {
        $this->responder(SituacaoConvite::Aceito);
    }

    public function recusar(): void
    {
        $this->responder(SituacaoConvite::Recusado);
    }

    public function cancelar(): void
    {
        $this->responder(SituacaoConvite::Cancelado);
    }

    private function responder(SituacaoConvite $resposta): void
    {
        if (!$this->pendente()) {
            throw new RegraViolada('O convite não está mais pendente.');
        }

        $this->situacao = $resposta;
    }
}
