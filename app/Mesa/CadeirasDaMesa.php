<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\SituacaoPartida;
use App\Models\User;

/**
 * Lugares da mesa de jogo (`x-mesa-de-jogo`). Quem olha senta sempre embaixo, no centro.
 * Nomes de exibição, nunca o identificador; o `id` da cadeira serve para a presença acender.
 */
final class CadeirasDaMesa
{
    public const LUGARES = 8;

    /** Ordem de preenchimento: você embaixo; depois em frente, nas laterais e nos cantos. */
    private const POSICOES = [
        'baixo-centro',
        'cima-centro',
        'esquerda',
        'direita',
        'cima-esquerda',
        'cima-direita',
        'baixo-esquerda',
        'baixo-direita',
    ];

    /**
     * @param  list<array{id: string, nome: string, pessoa?: string}>  $convitesPendentes  Só chegam para o admin.
     * @param  ?string  $urlDaVaga  Para onde a vaga leva o admin, que é quem convida.
     * @return array{0: list<array{posicao: string, tipo: string, id: ?string, nome: ?string, rotulo: string, iniciais: string, foto: ?string, admin: bool, local: bool, marca: ?string, url: ?string}>, 1: int}  As cadeiras e quantas pessoas ficaram sem lugar.
     */
    public static function de(Mesa $mesa, string $usuario, array $convitesPendentes = [], ?string $urlDaVaga = null): array
    {
        $marcas = self::marcasDaPartida($mesa);
        $ehAdmin = $mesa->admin() === $usuario;
        $sentados = [];
        $outros = [];
        foreach (PessoasDaMesa::de($mesa) as $pessoa) {
            if ($pessoa['id'] === $usuario) {
                $sentados[] = ['voce', $pessoa];
            } else {
                $outros[] = ['pessoa', $pessoa];
            }
        }
        foreach ($convitesPendentes as $convite) {
            // A cadeira leva o id de quem foi convidado, para acender pela presença.
            $outros[] = ['convite', ['id' => $convite['pessoa'] ?? $convite['id'], 'nome' => $convite['nome'], 'local' => false]];
        }

        $ocupacao = [...$sentados, ...$outros];
        $lugares = $mesa->quantidadeLugares();
        $posicoes = match ($lugares) {
            3 => ['baixo-centro', 'cima-esquerda', 'cima-direita'],
            5 => ['baixo-centro', 'cima-esquerda', 'cima-direita', 'esquerda', 'direita'],
            6 => ['baixo-centro', 'cima-centro', 'cima-esquerda', 'cima-direita', 'baixo-esquerda', 'baixo-direita'],
            7 => ['baixo-centro', 'cima-esquerda', 'cima-direita', 'esquerda', 'direita', 'baixo-esquerda', 'baixo-direita'],
            default => array_slice(self::POSICOES, 0, $lugares),
        };
        $excedentes = max(0, count($ocupacao) - $lugares);
        $ocupacao = array_slice($ocupacao, 0, $lugares);

        $contas = [];
        foreach ($ocupacao as [, $pessoa]) {
            if (! $pessoa['local']) {
                $contas[] = $pessoa['id'];
            }
        }
        $fotos = User::query()->whereIn('id', $contas)->pluck('google_avatar_url', 'id');

        $cadeiras = [];
        foreach ($posicoes as $indice => $posicao) {
            if (! isset($ocupacao[$indice])) {
                // Vaga só leva a algum lugar para o admin, que é quem convida.
                $cadeiras[] = self::vaga($posicao, $ehAdmin ? $urlDaVaga : null);

                continue;
            }

            [$tipo, $pessoa] = $ocupacao[$indice];
            $cadeiras[] = self::cadeira(
                $posicao,
                $tipo,
                $pessoa['id'],
                $pessoa['nome'],
                $tipo !== 'convite' && $pessoa['id'] === $mesa->admin(),
                (bool) $pessoa['local'],
                $tipo === 'convite' ? null : ($marcas[$pessoa['id']] ?? null),
                $tipo === 'convite' && $ehAdmin ? $urlDaVaga : null,
                $pessoa['local'] ? null : $fotos->get($pessoa['id']),
            );
        }

        return [$cadeiras, $excedentes];
    }

    /** @return array<string, mixed> */
    private static function cadeira(string $posicao, string $tipo, ?string $id, ?string $nome, bool $admin, bool $local, ?string $marca, ?string $url, ?string $foto): array
    {
        $rotulo = match ($tipo) {
            'voce' => 'Você',
            'convite' => 'Convite',
            default => self::primeiroNome((string) $nome),
        };

        return [
            'posicao' => $posicao,
            'tipo' => $tipo,
            'id' => $id,
            'nome' => $nome,
            'rotulo' => $rotulo,
            'iniciais' => $nome === null ? '' : PessoasDaMesa::iniciais($nome),
            'foto' => $foto,
            'admin' => $admin,
            'local' => $local,
            'marca' => $marca,
            'url' => $url,
        ];
    }

    /** @return array<string, mixed> */
    private static function vaga(string $posicao, ?string $url): array
    {
        return [
            'posicao' => $posicao,
            'tipo' => 'vaga',
            'id' => null,
            'nome' => null,
            'rotulo' => $url === null ? 'Livre' : 'Convidar',
            'iniciais' => '+',
            'foto' => null,
            'admin' => false,
            'local' => false,
            'marca' => null,
            'url' => $url,
        ];
    }

    /**
     * Sequencial: quem está na vez. Paralelo: quem ainda não jogou a rodada. Na pausa, nada.
     *
     * @return array<string, string>
     */
    private static function marcasDaPartida(Mesa $mesa): array
    {
        $partida = $mesa->partidaAtiva();
        if ($partida === null || $partida->situacao() !== SituacaoPartida::EmAndamento || $partida->pausada()) {
            return [];
        }

        if ($partida->modo() === ModoExecucao::Paralelo) {
            return array_fill_keys($partida->pendentesDaRodada(), 'Falta jogar');
        }

        $vez = $partida->vezAtual();

        return $vez === null ? [] : [$vez => 'Na vez'];
    }

    private static function primeiroNome(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];

        return $partes[0] ?? $nome;
    }
}
