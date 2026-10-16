<?php

declare(strict_types=1);

namespace App\Infra\Memoria;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\ParticipanteLocal;
use App\Dominio\Mesa\SituacaoReconhecimento;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\RepositorioDeMesas;

final class RepositorioDeMesasEmMemoria implements RepositorioDeMesas
{
    /** @var array<string, Mesa> */
    private array $mesas = [];

    private int $sequencia = 0;

    public function novoId(): string
    {
        return 'mesa-' . ++$this->sequencia;
    }

    public function buscar(string $id): ?Mesa
    {
        return $this->mesas[$id] ?? null;
    }

    public function salvar(Mesa $mesa): void
    {
        $this->mesas[$mesa->id] = $mesa;
    }

    public function mesasAbertasDe(string $usuario): array
    {
        return array_values(array_filter(
            $this->mesas,
            static fn (Mesa $mesa): bool => $mesa->membroAtivo($usuario) && $mesa->participanteLocal($usuario) === null,
        ));
    }

    public function travarPessoa(string $usuario): void {}

    public function idsComPartidaEmAndamento(): array
    {
        $ids = [];
        foreach ($this->mesas as $mesa) {
            if ($mesa->partidaAtiva()?->situacao() === SituacaoPartida::EmAndamento) {
                $ids[] = $mesa->id;
            }
        }

        return $ids;
    }

    public function participantesLocaisDoEmail(string $email): array
    {
        return $this->participantesLocais(
            static fn (ParticipanteLocal $p): bool => $p->pertenceAo($email) && $p->situacao === SituacaoReconhecimento::Pendente,
        );
    }

    public function participantesLocaisReconhecidosPor(string $usuario): array
    {
        return $this->participantesLocais(static fn (ParticipanteLocal $p): bool => $p->usuario === $usuario);
    }

    /**
     * @param callable(ParticipanteLocal): bool $filtro
     * @return list<ParticipanteLocal>
     */
    private function participantesLocais(callable $filtro): array
    {
        $encontrados = [];
        foreach ($this->mesas as $mesa) {
            foreach ($mesa->participantesLocais() as $participante) {
                if ($filtro($participante)) {
                    $encontrados[] = $participante;
                }
            }
        }

        return $encontrados;
    }
}
