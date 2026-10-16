<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use App\Dominio\Plataforma\Transacao;
use App\Dominio\RegraViolada;
use App\Dominio\Relogio;
use stdClass;

/**
 * @phpstan-type Mapa array{id: int, nome: string, linhas: list<string>, ativo: bool, revisao: int, versao_publicada: int|null, usos_em_rodadas: int|null}
 */
final class CatalogoDeMapasLabirinto
{
    public function __construct(private RepositorioMapasLabirinto $repositorio, private Transacao $transacao, private Relogio $relogio) {}

    /** @return list<Mapa> */
    public function listar(): array
    {
        return $this->repositorio->listar();
    }

    /** @return Mapa */
    public function buscar(int $id): array
    {
        return $this->repositorio->buscar($id);
    }

    /** @return list<stdClass> */
    public function versoes(int $id): array
    {
        return $this->repositorio->versoes($id);
    }

    /** @param list<string> $linhas
     * @return Mapa
     */
    public function salvar(?int $id, int $revisao, string $nome, array $linhas, bool $publicar, ?int $autor): array
    {
        $nome = IdentidadeMapaLabirinto::nome($nome);
        DesenhoLabirinto::validarFormato($linhas);
        $definicao = $publicar ? DesenhoLabirinto::definicao($linhas) : null;
        try {
            return $this->transacao->executar(function () use ($id, $revisao, $nome, $linhas, $definicao, $autor): array {
                $mapa = $id === null ? ['id' => 0, 'nome' => $nome, 'linhas' => $linhas, 'ativo' => false, 'revisao' => 1, 'versao_publicada' => null, 'usos_em_rodadas' => null] : $this->bloquear($id, $revisao);
                $conflito = $this->conflito($id, $nome, $linhas);
                if ($conflito !== null) {
                    throw new RegraViolada($conflito['mensagem']);
                }
                $mapa['nome'] = $nome;
                $mapa['linhas'] = $linhas;
                if ($id !== null) {
                    $mapa['revisao']++;
                }
                if ($definicao !== null) {
                    $mapa['versao_publicada'] = ($mapa['versao_publicada'] ?? 0) + 1;
                    $mapa['ativo'] = true;
                }
                $agora = $this->relogio->agora();
                $id = $this->repositorio->gravar($id, $mapa, IdentidadeMapaLabirinto::chaveNome($nome), $agora);
                $hash = IdentidadeMapaLabirinto::chaveDesenho($linhas);
                $this->repositorio->reservar($id, $hash);
                if ($definicao !== null) {
                    $this->repositorio->publicar($id, $mapa['versao_publicada'], $nome, $definicao, $autor, $agora);
                }
                // Desenhos publicados ficam reservados, mesmo após desativar ou editar.
                $reservados = [$hash];
                foreach ($this->repositorio->versoes($id) as $versao) {
                    /** @var array{linhas: list<string>} $desenho */
                    $desenho = json_decode($versao->desenho, true, 512, JSON_THROW_ON_ERROR);
                    $reservados[] = IdentidadeMapaLabirinto::chaveDesenho($desenho['linhas']);
                }
                $this->repositorio->liberarRascunhos($id, $reservados);

                return $this->repositorio->buscar($id);
            });
        } catch (ColisaoMapa) {
            throw new RegraViolada('Outro mapa já usa esse nome ou desenho. Atualize a lista e abra o mapa existente.');
        }
    }

    /** @param list<string> $linhas
     * @return array{id: int, mensagem: string}|null
     */
    public function conflito(?int $id, string $nome, array $linhas): ?array
    {
        $conflito = $this->repositorio->conflito($id, IdentidadeMapaLabirinto::chaveNome($nome), IdentidadeMapaLabirinto::chaveDesenho($linhas));
        if ($conflito === null) {
            return null;
        }
        $mapa = '#'.$conflito['id'].': '.$conflito['nome'].'.';

        return ['id' => $conflito['id'], 'mensagem' => $conflito['tipo'] === 'nome'
            ? 'Esse nome já pertence ao mapa '.$mapa
            : 'Esse desenho já pertence ao mapa '.$mapa.' Altere uma célula para criar outro mapa.'];
    }

    /** @return Mapa */
    public function alternarAtivo(int $id, int $revisao): array
    {
        return $this->transacao->executar(function () use ($id, $revisao): array {
            $mapa = $this->bloquear($id, $revisao);
            if ($mapa['versao_publicada'] === null) {
                throw new RegraViolada('Publique uma versão antes de ativar o mapa.');
            }
            $mapa['ativo'] = ! $mapa['ativo'];
            $mapa['revisao']++;
            $this->repositorio->gravar($id, $mapa, IdentidadeMapaLabirinto::chaveNome($mapa['nome']), $this->relogio->agora());

            return $this->repositorio->buscar($id);
        });
    }

    /** @return Mapa */
    private function bloquear(int $id, int $revisao): array
    {
        $mapa = $this->repositorio->bloquear($id);
        if ($mapa['revisao'] !== $revisao) {
            throw new RegraViolada('Este mapa foi alterado em outra aba. Para preservar suas alterações, use Duplicar rascunho; ajuste o nome e o desenho antes de salvar a cópia. Reabrir o mapa descarta as alterações não salvas.');
        }

        return $mapa;
    }
}
