<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use App\Dominio\Plataforma\Transacao;
use App\Dominio\Relogio;
use DateTimeImmutable;
use DateTimeZone;
use stdClass;

final class SoloLabirinto
{
    public function __construct(private RepositorioSoloLabirinto $repositorio, private Transacao $transacao, private Relogio $relogio) {}

    /** @return list<stdClass> */
    public function disponiveis(int $pessoa): array
    {
        return $this->repositorio->disponiveis($pessoa);
    }

    public function buscar(int $pessoa, string $id): stdClass
    {
        return $this->repositorio->buscar($pessoa, $id);
    }

    public function iniciar(int $pessoa, ?int $mapa): string
    {
        return $this->transacao->executar(function () use ($pessoa, $mapa): string {
            $versao = $this->repositorio->versaoDisponivel($mapa);
            if ($versao === null) {
                throw new FalhaSolo('Este mapa não está disponível. Escolha outro mapa ativo.', 'mapa');
            }

            return $this->repositorio->criar($pessoa, $versao, $this->relogio->agora());
        });
    }

    public function concluir(int $pessoa, string $id, int $tempo): stdClass
    {
        return $this->transacao->executar(function () use ($pessoa, $id, $tempo): stdClass {
            // Bloquear antes de qualquer leitura consistente da tentativa ou dos recordes.
            $this->repositorio->travar($pessoa, $id);
            $tentativa = $this->repositorio->buscar($pessoa, $id);
            $agora = $this->relogio->agora();
            $inicio = new DateTimeImmutable($tentativa->iniciada_em, new DateTimeZone('UTC'));
            /** @var array{linhas: list<string>, tamanho_celula: int, velocidade: int} $desenho */
            $desenho = json_decode($tentativa->desenho, true, 512, JSON_THROW_ON_ERROR);
            TentativaSolo::validar($tempo, $tentativa->tempo === null ? null : (int) $tentativa->tempo, $agora->getTimestamp() - $inicio->getTimestamp(), $desenho);
            if ($tentativa->tempo === null) {
                $melhor = $this->repositorio->melhor($pessoa, (int) $tentativa->versao_id);
                $this->repositorio->registrar($id, $tempo, TentativaSolo::recorde($tempo, $melhor), $agora);
            }

            return $this->repositorio->buscar($pessoa, $id);
        });
    }

    /** @return array{melhorTempo: int|null, melhores: list<stdClass>, ultimas: list<stdClass>} */
    public function historico(int $pessoa, int $versao): array
    {
        return $this->repositorio->historico($pessoa, $versao);
    }

    public function limparExpiradas(): int
    {
        return $this->repositorio->removerAbertasAntesDe($this->relogio->agora()->modify('-'.TentativaSolo::VALIDADE_SEGUNDOS.' seconds'));
    }
}
