<?php

declare(strict_types=1);

namespace App\Dominio\Mesa;

use App\Dominio\RegraViolada;

/**
 * Pessoa cadastrada pelo admin por nome e e-mail, jogando na sessão e no aparelho do admin.
 * A participação só entra no histórico oficial de uma conta depois de reconhecida.
 */
final class ParticipanteLocal
{
    public readonly string $email;

    public private(set) SituacaoReconhecimento $situacao = SituacaoReconhecimento::Pendente;

    public private(set) ?string $usuario = null;

    public function __construct(
        public readonly string $id,
        public readonly string $nome,
        string $email,
    ) {
        if (trim($nome) === '') {
            throw new RegraViolada('O nome do participante local é obrigatório.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RegraViolada('O e-mail do participante local é obrigatório e precisa ser válido.');
        }

        $this->email = mb_strtolower($email);
    }

    public function pertenceAo(string $email): bool
    {
        return $this->email === mb_strtolower($email);
    }

    public function reconhecer(string $usuario, string $emailDoUsuario): void
    {
        $this->exigirPendenteDo($emailDoUsuario);
        $this->situacao = SituacaoReconhecimento::Reconhecida;
        $this->usuario = $usuario;
    }

    public function rejeitar(string $emailDoUsuario): void
    {
        $this->exigirPendenteDo($emailDoUsuario);
        $this->situacao = SituacaoReconhecimento::Rejeitada;
    }

    private function exigirPendenteDo(string $email): void
    {
        if (!$this->pertenceAo($email)) {
            throw new RegraViolada('A participação local pertence a outro e-mail.');
        }
        if ($this->situacao !== SituacaoReconhecimento::Pendente) {
            throw new RegraViolada('A participação local já foi respondida.');
        }
    }
}
