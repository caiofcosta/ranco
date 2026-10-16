<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\ParticipanteLocal;

interface RepositorioDeMesas
{
    public function novoId(): string;

    public function buscar(string $id): ?Mesa;

    public function salvar(Mesa $mesa): void;

    /** @return list<Mesa> Mesas abertas em que a pessoa é membro ativo (participante local não conta). */
    public function mesasAbertasDe(string $usuario): array;

    /** Serializa, dentro da transação, as ações da mesma pessoa em mesas diferentes. */
    public function travarPessoa(string $usuario): void;

    /** @return list<string> */
    public function idsComPartidaEmAndamento(): array;

    /** @return list<ParticipanteLocal> */
    public function participantesLocaisDoEmail(string $email): array;

    /** @return list<ParticipanteLocal> */
    public function participantesLocaisReconhecidosPor(string $usuario): array;
}
