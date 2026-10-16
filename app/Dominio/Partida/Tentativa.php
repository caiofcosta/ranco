<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

final class Tentativa
{
    public ?\DateTimeImmutable $liberaEm = null;

    public ?\DateTimeImmutable $prazoExpiraEm = null;

    public function __construct(?\DateTimeImmutable $inicio = null, ?int $prazoSegundos = null)
    {
        if ($inicio !== null && $prazoSegundos !== null) {
            // Preparação de 3 s fora do prazo útil: o prazo conta a partir de liberaEm.
            $this->liberaEm = $inicio->modify('+3 seconds');
            $this->prazoExpiraEm = $this->liberaEm->modify("+{$prazoSegundos} seconds");
        }
    }

    public private(set) SituacaoTentativa $situacao = SituacaoTentativa::EmAndamento;

    public private(set) int $resultado = 0;

    public private(set) ?int $tempoMedido = null;

    /** Sequência: desafios concluídos. A pontuação deriva deste número, não do cliente. */
    public int $desafiosConcluidos = 0;

    /** Sequência: respostas já dadas no desafio atual, para recuperar a vez após reconexão. */
    public array $respostasSequencia = [];

    /** Sequência: quando o desafio atual foi exibido; base da duração mínima e da reconciliação. */
    public ?\DateTimeImmutable $desafioExibidoEm = null;

    /** Sequência: chave pública que valida os quadros da transmissão desta vez (a privada fica no navegador do jogador). */
    public ?string $chaveTransmissao = null;

    public function registrarProgresso(int $resultadoParcial): void
    {
        $this->resultado = $resultadoParcial;
    }

    public function concluir(int $resultado, ?int $tempoMedido = null): void
    {
        $this->resultado = $resultado;
        $this->tempoMedido = $tempoMedido;
        $this->situacao = SituacaoTentativa::Concluida;
    }

    public function interromper(): void
    {
        $this->situacao = SituacaoTentativa::Interrompida;
    }
}
