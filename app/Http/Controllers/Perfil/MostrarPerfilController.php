<?php

declare(strict_types=1);

namespace App\Http\Controllers\Perfil;

use App\Dominio\Conquista\RepositorioDeMedalhas;
use App\Dominio\Conquista\RepositorioDeTrofeus;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocador;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MostrarPerfilController
{
    public function __invoke(
        Request $pedido,
        Plataforma $plataforma,
        RepositorioDeMedalhas $repositorio,
        Provocador $provocador,
        RepositorioDeTrofeus $repositorioDeTrofeus,
        ?string $pessoa = null,
    ): Response {
        $usuario = $pedido->user();
        $proprio = $pessoa === null || $pessoa === $usuario->identificadorNoDominio();
        if (! $proprio) {
            // Não usar binding de User: conta inexistente deve ter o mesmo 403 do não amigo.
            if (preg_match('/^[1-9][0-9]*$/D', $pessoa) !== 1
                || ! $plataforma->saoAmigos($usuario->identificadorNoDominio(), $pessoa)) {
                return $this->privado($pedido, $provocador);
            }
            $usuario = User::query()->select(['id', 'name', 'google_avatar_url'])->find($pessoa);
            if ($usuario === null) {
                return $this->privado($pedido, $provocador);
            }
        }

        $participacoesReconhecidas = $plataforma->participacoesReconhecidasDe($usuario->identificadorNoDominio());
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $medalhas = [];
        foreach ($this->ordenar(array_map(static fn (array $medalha): array => [
            'id' => $medalha['id'],
            'colocacao' => $medalha['colocacao'],
            'empate' => $medalha['empate'],
            'jogo' => $medalha['jogo'],
            'frase' => $medalha['frase'],
            'mesa' => $medalha['mesa'],
            'mostradaEm' => $medalha['partidaCriadaEm'] ?? $medalha['concedidaEm'],
        ], $plataforma->medalhasDe($usuario->identificadorNoDominio(), $repositorio, $participacoesReconhecidas))) as $medalha) {
            $data = $medalha['mostradaEm']->setTimezone($fuso);
            $medalhas[] = [
                'colocacao' => $medalha['colocacao'],
                'empate' => $medalha['empate'],
                'jogo' => CatalogoDeJogos::nome($medalha['jogo']),
                'logo' => CatalogoDeJogos::logo($medalha['jogo']),
                'frase' => $medalha['frase'],
                'mesa' => $medalha['mesa'],
                'data' => $data->format('d/m/Y'),
                'dataIso' => $data->format(DATE_ATOM),
            ];
        }

        $trofeus = [];
        foreach ($this->ordenar(array_map(static fn (array $trofeu): array => [
            'id' => $trofeu['id'],
            'tipo' => $trofeu['tipo'],
            'mesa' => $trofeu['mesa'],
            'frase' => $trofeu['frase'],
            'mostradaEm' => $trofeu['mesaCriadaEm'] ?? $trofeu['concedidoEm'],
        ], $plataforma->trofeusDe($usuario->identificadorNoDominio(), $repositorioDeTrofeus, $participacoesReconhecidas))) as $trofeu) {
            $trofeus[] = [
                'tipo' => $trofeu['tipo'],
                'mesa' => $trofeu['mesa'],
                'frase' => $trofeu['frase'],
                'data' => $trofeu['mostradaEm']->setTimezone($fuso)->format('d/m/Y'),
            ];
        }

        return response()->view('perfil.mostrar', [
            'privado' => false,
            'proprio' => $proprio,
            'nome' => $usuario->name,
            'foto' => $usuario->google_avatar_url,
            'medalhas' => $medalhas,
            'trofeus' => $trofeus,
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * @param list<array{id: int, mostradaEm: \DateTimeImmutable}> $itens
     * @return list<array{id: int, mostradaEm: \DateTimeImmutable}>
     */
    private function ordenar(array $itens): array
    {
        usort($itens, static fn (array $a, array $b): int => $b['mostradaEm'] <=> $a['mostradaEm'] ?: $b['id'] <=> $a['id']);

        return $itens;
    }

    private function privado(Request $pedido, Provocador $provocador): Response
    {
        // A frase é estável na sessão e não depende do alvo nem da existência da conta.
        if (! $pedido->session()->has('perfil.provocacao_privada')) {
            $pedido->session()->put('perfil.provocacao_privada', $provocador->sortear(Momento::PerfilPrivado) ?? '');
        }

        return response()->view('perfil.mostrar', [
            'privado' => true,
            'provocacao' => $pedido->session()->get('perfil.provocacao_privada'),
        ], 403)->header('Cache-Control', 'no-store, private');
    }
}
