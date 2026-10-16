<?php

declare(strict_types=1);

namespace App\Provocacao;

use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocador;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;
use App\Models\MomentoDeProvocacao;

/** Sorteia uma frase do momento com valores de exemplo, só para a curadoria no Painel. */
final class SorteiaExemploDoMomento
{
    /** @var array<string, string> */
    private const EXEMPLOS = [
        'nome' => 'Ana',
        'admin' => 'Ana',
        'vencedor' => 'Ana',
        'outro' => 'Bruno',
        'convidado' => 'Ana',
        'tentativa' => '3',
        'minutos' => '5',
        'rodada' => '2',
    ];

    public function __construct(
        private readonly Provocador $provocador,
    ) {}

    public static function criar(): self
    {
        return new self(new Provocador(app(RepositorioDeProvocacoes::class)));
    }

    public function sortear(MomentoDeProvocacao $momento): ?string
    {
        $doDominio = Momento::from($momento->codigo);
        $valores = [];
        foreach ($momento->variaveisOferecidas() as $nome) {
            $valores[$nome] = self::EXEMPLOS[$nome] ?? 'X';
        }

        return $this->provocador->sortear($doDominio, 2, $valores, Perspectiva::Pessoa)
            ?? $this->provocador->sortear($doDominio, 2, $valores, Perspectiva::Mesa);
    }
}
