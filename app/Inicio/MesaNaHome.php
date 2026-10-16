<?php

declare(strict_types=1);

namespace App\Inicio;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Mesa\NomeDaMesa;
use App\Mesa\PessoasDaMesa;
use Illuminate\Support\Facades\Route;

/** A mesa atual numa linha do Início: nome, quem criou, situação e a ação. Nomes de exibição, nunca o identificador. */
final readonly class MesaNaHome
{
    public function __construct(
        public string $id,
        public string $nome,
        public string $rotulo,
        public ?string $detalhe,
        public int $quantasPessoas,
        public ?string $criadaPor,
        public string $acaoRotulo = 'Abrir mesa',
        public ?string $acaoUrl = null,
        public bool $acaoEJogar = false,
    ) {}

    public static function de(Mesa $mesa, string $usuario): self
    {
        $quantasPessoas = count(PessoasDaMesa::de($mesa));
        $joga = self::podeJogarAgora($mesa, $usuario);

        return new self(
            $mesa->id,
            NomeDaMesa::de($mesa),
            self::rotulo($mesa, $usuario),
            self::detalhe($mesa, $quantasPessoas),
            $quantasPessoas,
            $mesa->admin() === $usuario ? 'você' : PessoasDaMesa::nome($mesa, $mesa->admin()),
            $joga ? 'Jogar minha vez' : 'Abrir mesa',
            $joga ? self::rotaParaJogar($mesa) : self::rota('mesas.mostrar', $mesa->id),
            $joga,
        );
    }

    private static function podeJogarAgora(Mesa $mesa, string $usuario): bool
    {
        $partida = $mesa->partidaAtiva();

        return $partida !== null && ! $partida->pausada() && $partida->podeJogarAgora($usuario);
    }

    private static function rota(string $nome, string $mesaId): string
    {
        return Route::has($nome) ? route($nome, $mesaId) : url('/mesas/'.$mesaId);
    }

    private static function rotaParaJogar(Mesa $mesa): string
    {
        return self::rota('mesas.jogar', $mesa->id);
    }

    private static function rotulo(Mesa $mesa, string $usuario): string
    {
        $partida = $mesa->partidaAtiva();
        if ($partida?->pausada()) {
            return 'Pausada';
        }
        if ($partida?->situacao() === SituacaoPartida::EmAndamento && self::podeJogarAgora($mesa, $usuario)) {
            return 'Sua vez';
        }
        if ($partida?->situacao() === SituacaoPartida::EmAndamento) {
            return 'Em jogo';
        }
        if ($partida?->situacao() === SituacaoPartida::EmConfiguracao) {
            return 'Configurando';
        }

        return 'Aguardando';
    }

    /** Ao lado da situação: a rodada da partida em andamento ou o aviso de que só tem você. */
    private static function detalhe(Mesa $mesa, int $quantasPessoas): ?string
    {
        $partida = $mesa->partidaAtiva();
        if ($partida?->situacao() === SituacaoPartida::EmAndamento) {
            $limite = $partida->configuracao()->limiteRodadas;

            return 'Rodada '.$partida->rodadaAtual().($limite === null ? '' : ' de '.$limite);
        }
        if ($partida?->situacao() !== SituacaoPartida::EmConfiguracao && $quantasPessoas < 2) {
            return 'Só você por enquanto';
        }

        return null;
    }
}
