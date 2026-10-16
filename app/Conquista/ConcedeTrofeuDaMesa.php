<?php

declare(strict_types=1);

namespace App\Conquista;

use App\Dominio\Conquista\ConcessorDeTrofeus;
use App\Dominio\Conquista\PremiadosDoRanking;
use App\Dominio\Conquista\RepositorioDeTrofeus;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocador;
use App\Dominio\Relogio;
use App\Mesa\NomeDaMesa;
use App\Mesa\PessoasDaMesa;
use App\Mesa\RankingDaMesa;

final class ConcedeTrofeuDaMesa implements ConcessorDeTrofeus
{
    public function __construct(
        private readonly RepositorioDeTrofeus $trofeus,
        private readonly Relogio $relogio,
        private readonly Provocador $provocador,
    ) {}

    public function concederPendentes(Mesa $mesa): void
    {
        if ($mesa->situacao() !== SituacaoMesa::Encerrada) {
            return;
        }

        // A concessao inteira e gravada de uma vez; leituras posteriores nao recalculam os premiados.
        if ($this->trofeus->daMesa($mesa->id) !== []) {
            return;
        }

        PessoasDaMesa::preparar($mesa);
        $premiados = PremiadosDoRanking::de($mesa->situacao(), RankingDaMesa::de($mesa)->linhas);
        if ($premiados === []) {
            return;
        }

        $comFrase = [];
        foreach ($premiados as $premiado) {
            $premiado['frase'] = $this->fraseDe($mesa, $premiados, $premiado);
            $comFrase[] = $premiado;
        }

        $this->trofeus->conceder(
            $mesa->id,
            NomeDaMesa::de($mesa),
            $comFrase,
            $this->relogio->agora(),
            $mesa->criadaEm(),
        );
    }

    /**
     * @param list<array{pessoa: string, tipo: string}> $premiados
     * @param array{pessoa: string, tipo: string} $premiado
     */
    private function fraseDe(Mesa $mesa, array $premiados, array $premiado): ?string
    {
        // Empate em 2º/3º: mesmo tipo de troféu para mais de uma pessoa; muda só a frase, sem nomes.
        $empatouNoLugar = count(array_filter(
            $premiados,
            static fn (array $outro): bool => $outro['tipo'] === $premiado['tipo'],
        )) > 1;
        $momento = match ($premiado['tipo']) {
            'vitoria' => Momento::TrofeuVitoria,
            'segundo' => $empatouNoLugar ? Momento::TrofeuSegundoEmpate : Momento::TrofeuSegundo,
            'terceiro' => $empatouNoLugar ? Momento::TrofeuTerceiroEmpate : Momento::TrofeuTerceiro,
            default => Momento::TrofeuEmpate,
        };

        if ($momento !== Momento::TrofeuEmpate) {
            return $this->provocador->sortear($momento, 1, [], Perspectiva::Pessoa);
        }

        $variaveis = [];
        $nomes = [];
        foreach ($premiados as $outro) {
            // Só os outros empatados no topo; 2º e 3º também estão entre os premiados.
            if ($outro['pessoa'] === $premiado['pessoa'] || $outro['tipo'] !== 'empate') {
                continue;
            }
            $nome = PessoasDaMesa::nome($mesa, $outro['pessoa']);
            if ($nome === null) {
                $nomes = [];
                break;
            }
            $nomes[] = $nome;
        }
        if ($nomes !== []) {
            $variaveis['outro'] = PessoasDaMesa::juntar($nomes);
        }

        return $this->provocador->sortear(Momento::TrofeuEmpate, 1, $variaveis, Perspectiva::Pessoa);
    }
}
