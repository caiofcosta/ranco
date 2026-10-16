<?php

declare(strict_types=1);

namespace App\Livewire\Mesa;

use App\Dominio\ConflitoDeConcorrencia;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Http\Controllers\Mesa\GaranteMembroDaMesa;
use App\Jogos\JogosDisponiveis;
use App\Mesa\CadeirasDaMesa;
use App\Mesa\ClassificacaoDaMesa;
use App\Mesa\ConvitesDaMesa;
use App\Mesa\HistoricoDaMesa;
use App\Mesa\PartidaNaMesa;
use App\Mesa\PessoasDaMesa;
use App\Mesa\RankingDaMesa;
use App\Mesa\ResultadoDaPartida;
use App\Mesa\TelaDaMesa;
use App\Models\ModalDeNotificacao;
use App\Models\User;
use App\Servicos\RepositorioDeModaisDeNotificacao;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class MostrarMesa extends Component
{
    use GaranteMembroDaMesa;

    #[Locked]
    public string $id;

    /** Número da partida cujo resultado abre na chegada; fixo para a próxima partida não trocá-lo. */
    #[Locked]
    public ?int $resultado = null;

    public mixed $prazo_vez_segundos = null;

    // Nulo aqui de propósito: um padrão de classe volta a aparecer depois de cada ida ao servidor, mesmo quando o usuário apagou o campo. O padrão entra no mount().
    public mixed $meta_pontos = null;

    public mixed $limite_rodadas = null;

    public mixed $modo = null;

    public string $jogo = CatalogoDeJogos::LABIRINTO;

    /** @var list<int> */
    public array $pontos_por_posicao = [3, 2, 1, 0];

    public mixed $quantidade_lugares = 8;

    public function abrirLugares(): void
    {
        $mesa = $this->mesaDoAdmin(app(Plataforma::class), $this->id);
        $this->quantidade_lugares = $mesa->quantidadeLugares();
        $this->resetErrorBag();
        $this->dispatch('escolher-lugares');
    }

    public function salvarLugares(): void
    {
        $this->executar(function (Plataforma $plataforma, string $por): void {
            $dados = $this->validate([
                'quantidade_lugares' => ['required', 'integer', 'min:2', 'max:8'],
            ]);
            $plataforma->configurarLugares($this->id, (int) $dados['quantidade_lugares'], $por);
            $this->dispatch('lugares-confirmados');
        });
    }

    public function boot(): void
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->hasVerifiedEmail(), 403);
    }

    public function mount(string $id, ?int $resultado = null): void
    {
        $this->id = $id;
        $this->resultado = $resultado;
        $this->prazo_vez_segundos = old('prazo_vez_segundos');
        $this->meta_pontos = old('meta_pontos', CatalogoDeJogos::META_PADRAO_PONTOS);
        $this->limite_rodadas = old('limite_rodadas');
        $this->modo = old('modo', CatalogoDeJogos::modos(CatalogoDeJogos::LABIRINTO)[0]->value);
        $this->jogo = old('jogo', app(JogosDisponiveis::class)->idsDisponiveis()[0] ?? CatalogoDeJogos::LABIRINTO);
    }

    /** Entrada para o cliente de tempo real; os dados continuam vindo do servidor. */
    #[On('mesa-atualizada.{id}')]
    public function atualizarMesa(): void
    {
        // O render autoriza novamente o acesso e consulta o estado atual.
    }

    public function abrirEscolhaDoJogo(): void
    {
        $mesa = $this->mesaDoAdmin(app(Plataforma::class), $this->id);
        $configuracao = $mesa->partidaAtiva()?->configuracao();
        $padrao = CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null)->pontosPorPosicao;
        $pontos = $configuracao?->pontosPorPosicao ?? $padrao;
        $this->pontos_por_posicao = array_slice(array_pad($pontos, 4, 0), 0, 4);
        $this->prazo_vez_segundos = $configuracao?->prazoVezSegundos;
        $this->meta_pontos = $configuracao !== null ? $configuracao->metaPontos : CatalogoDeJogos::META_PADRAO_PONTOS;
        $this->limite_rodadas = $configuracao?->limiteRodadas;
        $this->modo = ($configuracao?->modo ?? CatalogoDeJogos::modos(CatalogoDeJogos::LABIRINTO)[0])->value;
        $this->jogo = $configuracao?->jogo ?? CatalogoDeJogos::LABIRINTO;
        $this->resetErrorBag();
        $this->dispatch('escolher-jogo');
    }

    public function salvarConfiguracao(?string $jogo = null): void
    {
        $this->jogo = $jogo ?? $this->jogo;
        $this->gravarConfiguracao(true);
    }

    public function criarPartida(?string $jogo = null): void
    {
        $this->jogo = $jogo ?? $this->jogo;
        $this->gravarConfiguracao(false);
    }

    private function gravarConfiguracao(bool $editar): void
    {
        $this->executar(function (Plataforma $plataforma, string $por) use ($editar): void {
            foreach (['prazo_vez_segundos', 'meta_pontos', 'limite_rodadas'] as $campo) {
                if ($this->{$campo} === '') {
                    $this->{$campo} = null;
                }
            }

            $dados = $this->validate([
                'pontos_por_posicao' => ['required', 'array', 'list', 'size:4'],
                'pontos_por_posicao.*' => ['required', 'integer', 'min:0', 'max:2147483647'],
                'prazo_vez_segundos' => ['nullable', 'integer', 'min:1'],
                'meta_pontos' => ['nullable', 'integer', 'min:1', function ($attr, $val, $fail) {
                    if ($val !== null && $this->limite_rodadas !== null && $this->limite_rodadas !== '') {
                        $fail('Escolha apenas uma forma de encerramento: meta de pontos ou limite de rodadas.');
                    }
                }],
                'limite_rodadas' => ['nullable', 'integer', 'min:1', function ($attr, $val, $fail) {
                    if ($val !== null && $this->meta_pontos !== null && $this->meta_pontos !== '') {
                        $fail('Escolha apenas uma forma de encerramento: meta de pontos ou limite de rodadas.');
                    }
                }],
                'modo' => ['required', Rule::enum(ModoExecucao::class)->only(CatalogoDeJogos::modos($this->jogo))],
                'jogo' => ['required', Rule::in(app(JogosDisponiveis::class)->idsDisponiveis())],
            ], ['modo.*' => 'Escolha um modo aceito pelo jogo.']);

            $acao = $editar ? 'reconfigurarPartida' : 'criarPartida';
            $plataforma->{$acao}(
                $this->id,
                CatalogoDeJogos::configuracao(
                    $dados['jogo'],
                    isset($dados['prazo_vez_segundos']) ? (int) $dados['prazo_vez_segundos'] : null,
                    isset($dados['meta_pontos']) ? (int) $dados['meta_pontos'] : null,
                    isset($dados['limite_rodadas']) ? (int) $dados['limite_rodadas'] : null,
                    ModoExecucao::from($dados['modo']),
                    array_map('intval', $dados['pontos_por_posicao']) === [3, 2, 1, 0]
                        ? null
                        : array_pad(array_map('intval', $dados['pontos_por_posicao']), 8, (int) $dados['pontos_por_posicao'][3]),
                ),
                $por,
            );
            $this->reset('prazo_vez_segundos', 'meta_pontos', 'limite_rodadas');
            $this->modo = CatalogoDeJogos::modos(CatalogoDeJogos::LABIRINTO)[0]->value;
            $this->jogo = CatalogoDeJogos::LABIRINTO;
            $this->dispatch('jogo-confirmado');
        });
    }

    public function iniciarPartida(): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->iniciarPartida($this->id, $por));
    }

    public function pausarPartida(): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->pausarPartida($this->id, $por));
    }

    public function despausarPartida(): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->despausarPartida($this->id, $por));
    }

    public function pularVez(string $participante): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->pularVez($this->id, $participante, $por));
    }

    public function admitirNaPartida(string $participante): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->admitirNaPartida($this->id, $participante, $por));
    }

    public function removerDaPartida(string $participante): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->removerDaPartida($this->id, $participante, $por));
    }

    public function removerDaMesa(string $participante): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->removerDaMesa($this->id, $participante, $por));
    }

    public function encerrarPartida(): void
    {
        $this->executar(fn (Plataforma $plataforma, string $por) => $plataforma->encerrarPartida($this->id, $por));
    }

    public function dispensarOrientacao(bool $naoMostrar = false): void
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $plataforma = app(Plataforma::class);
        $mesa = $this->mesaParaVisualizar($plataforma, $this->id);
        $tipo = $this->tipoDaOrientacao($mesa->admin() === $usuario->identificadorNoDominio());
        $repositorio = app(RepositorioDeModaisDeNotificacao::class);
        $modal = ModalDeNotificacao::where('tipo', $tipo)->where('ativo', true)->first();
        if ($modal) {
            $repositorio->marcarComoDescartada($usuario, $modal, $naoMostrar);
        }
    }

    public function dispensarResultado(): void
    {
        $this->resultado = null;
    }

    private function tipoDaOrientacao(bool $ehAdmin): string
    {
        return $ehAdmin ? 'onboarding_mesa_admin' : 'onboarding_mesa_participante';
    }

    public function render(): View
    {
        $plataforma = app(Plataforma::class);
        /** @var User $quem */
        $quem = auth()->user();
        // Ficou invisível em outra aba: sai da mesa (e da presença) em vez de quebrar o redesenho.
        if ($plataforma->estaInvisivel($quem->identificadorNoDominio())) {
            $this->redirectRoute('dashboard');

            return view('livewire.mesa.mostrar', ['encerrada' => true]);
        }
        $mesa = $this->mesaParaVisualizar($plataforma, $this->id);
        if ($mesa->situacao() === SituacaoMesa::Encerrada) {
            $this->redirectRoute('mesas.mostrar', $this->id);

            return view('livewire.mesa.mostrar', ['encerrada' => true]);
        }

        if ($mesa->partidaAtiva()?->situacao() === SituacaoPartida::EmAndamento) {
            $this->redirectRoute('mesas.jogo', $this->id);

            return view('livewire.mesa.mostrar', ['encerrada' => true]);
        }

        /** @var User $usuario */
        $usuario = auth()->user();
        $identificador = $usuario->identificadorNoDominio();
        PessoasDaMesa::preparar($mesa);
        $convites = ConvitesDaMesa::de($plataforma, $mesa, $identificador);
        // A vaga leva o admin à seção de convites da própria tela.
        [$cadeiras, $excedentes] = CadeirasDaMesa::de($mesa, $identificador, $convites->pendentes, $convites->visivel ? '#convidar' : null);

        $ehAdmin = $mesa->admin() === $identificador;
        $repositorio = app(RepositorioDeModaisDeNotificacao::class);
        $modalOrientacao = $repositorio->obterModalParaUsuario($usuario, $this->tipoDaOrientacao($ehAdmin));

        $configuracaoJogo = $mesa->partidaAtiva()?->configuracao();
        $historico = HistoricoDaMesa::de($mesa);
        $partida = PartidaNaMesa::de($mesa, $identificador);
        $resultadoDaPartida = $this->resultado === null
            ? null
            : ResultadoDaPartida::de($mesa, $identificador, $this->resultado, $historico);
        // Já dá para começar a primeira partida: usado para não expandir "Preparar mesa" à toa.
        $mesaPreparada = $configuracaoJogo !== null && $partida->podeIniciar;

        return view('livewire.mesa.mostrar', [
            'encerrada' => false,
            'tela' => TelaDaMesa::de($mesa, $identificador),
            'cadeiras' => $cadeiras,
            'quantidadeLugares' => $mesa->quantidadeLugares(),
            'excedentes' => $excedentes,
            'partida' => $partida,
            'ranking' => RankingDaMesa::de($mesa),
            'classificacao' => ClassificacaoDaMesa::de($mesa),
            'historico' => $historico,
            'convites' => $convites,
            'pessoas' => PessoasDaMesa::de($mesa),
            'nomeAdmin' => PessoasDaMesa::nome($mesa, $mesa->admin()),
            'configuracaoJogo' => $configuracaoJogo,
            'padraoJogo' => CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null),
            'jogosDisponiveis' => app(JogosDisponiveis::class)->disponiveis(),
            'resultadoDaPartida' => $resultadoDaPartida,
            // Não empilha a orientação sobre o resultado da partida.
            'mostrarOrientacao' => $modalOrientacao !== null && $resultadoDaPartida === null,
            'temPartidasConcluidas' => $historico->partidas !== [],
            'mesaPreparada' => $mesaPreparada,
        ]);
    }

    /** @param callable(Plataforma, string): mixed $acao */
    private function executar(callable $acao): void
    {
        $plataforma = app(Plataforma::class);
        $this->mesaDoAdmin($plataforma, $this->id);
        /** @var User $usuario */
        $usuario = auth()->user();
        $this->resetErrorBag();

        try {
            $acao($plataforma, $usuario->identificadorNoDominio());
        } catch (RegraViolada $erro) {
            $this->addError('partida', $erro->getMessage());
        } catch (ConflitoDeConcorrencia) {
            $this->addError('partida', 'A mesa foi atualizada. Confira o estado atual e tente novamente.');
        }
    }
}
