<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Jogos\JogosDisponiveis;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CriarPartidaController
{
    use GaranteMembroDaMesa;

    public function __invoke(Request $request, Plataforma $plataforma, string $id): RedirectResponse
    {
        $this->mesaDoAdmin($plataforma, $id);
        /** @var User $usuario */
        $usuario = auth()->user();

        $jogosDisponiveis = app(JogosDisponiveis::class)->idsDisponiveis();
        $dados = $request->validate([
            'personalizar_pontos' => ['sometimes', 'boolean'],
            'pontos_por_posicao' => $request->boolean('personalizar_pontos') ? ['required', 'array', 'list', 'size:4'] : ['exclude'],
            'pontos_por_posicao.*' => $request->boolean('personalizar_pontos') ? ['required', 'integer', 'min:0', 'max:2147483647'] : ['exclude'],
            'prazo_vez_segundos' => ['nullable', 'integer', 'min:1'],
            'meta_pontos' => ['nullable', 'integer', 'min:1', function ($attr, $val, $fail) use ($request) {
                if ($val !== null && $request->filled('limite_rodadas')) {
                    $fail('Escolha apenas uma forma de encerramento: meta de pontos ou limite de rodadas.');
                }
            }],
            'limite_rodadas' => ['nullable', 'integer', 'min:1', function ($attr, $val, $fail) use ($request) {
                if ($val !== null && $request->filled('meta_pontos')) {
                    $fail('Escolha apenas uma forma de encerramento: meta de pontos ou limite de rodadas.');
                }
            }],
            'jogo' => ['nullable', Rule::in($jogosDisponiveis)],
            // Sem o campo (formulário antigo), vale o modo padrão do jogo.
            'modo' => ['nullable', Rule::enum(ModoExecucao::class)],
        ], ['modo.*' => 'Escolha um modo que o Labirinto aceita.']);
        $jogo = $dados['jogo'] ?? CatalogoDeJogos::LABIRINTO;
        if (! in_array($jogo, $jogosDisponiveis, true)) {
            throw ValidationException::withMessages(['jogo' => 'O jogo padrão não está disponível. Escolha um jogo ativo.']);
        }

        // Sem nenhum dos dois campos (requisição fora do formulário), vale a meta padrão.
        // Campo presente e vazio, como no formulário, significa "sem limite".
        $semCampos = ! array_key_exists('meta_pontos', $dados) && ! array_key_exists('limite_rodadas', $dados);
        $meta = $semCampos ? CatalogoDeJogos::META_PADRAO_PONTOS : (isset($dados['meta_pontos']) ? (int) $dados['meta_pontos'] : null);
        $limite = isset($dados['limite_rodadas']) ? (int) $dados['limite_rodadas'] : null;

        try {
            $plataforma->criarPartida(
                $id,
                CatalogoDeJogos::configuracao(
                    $jogo,
                    isset($dados['prazo_vez_segundos']) ? (int) $dados['prazo_vez_segundos'] : null,
                    $meta,
                    $limite,
                    isset($dados['modo']) ? ModoExecucao::from($dados['modo']) : null,
                    $request->boolean('personalizar_pontos') ? array_pad(array_map('intval', $dados['pontos_por_posicao']), 8, (int) $dados['pontos_por_posicao'][3]) : null,
                ),
                $usuario->identificadorNoDominio(),
            );
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withErrors(['partida' => $erro->getMessage()]);
        }

        return redirect()->route('mesas.mostrar', $id);
    }
}
