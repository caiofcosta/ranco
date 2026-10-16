<?php

declare(strict_types=1);

namespace App\Livewire\Mesa;

use App\Models\Opcao;
use App\Models\User;
use App\Opcoes\Lista;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Oferece nomes; a criação continua pelo POST e pelo controller existentes. */
final class FormularioCriarMesa extends Component
{
    public string $nome = '';

    public mixed $categoria = 'generico';

    /** @var list<array{id: int, nome: string}> */
    #[Locked]
    public array $sugestoes = [];

    public function boot(): void
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->hasVerifiedEmail(), 403);
    }

    public function mount(): void
    {
        $nomeAnterior = old('nome');
        $this->nome = is_string($nomeAnterior) ? $nomeAnterior : '';
        $this->outrasSugestoes();
        $this->setErrorBag(session('errors')?->getBag('default') ?? []);
    }

    public function updatedCategoria(): void
    {
        $this->sugestoes = [];
        $this->outrasSugestoes();
    }

    public function outrasSugestoes(): void
    {
        $this->resetErrorBag('sugestao');

        $dados = $this->validateOnly('categoria', [
            'categoria' => [
                'required',
                'string',
                Rule::in(array_keys(Opcao::CATEGORIAS_NOMES_DE_MESA)),
            ],
        ]);

        $anteriores = array_column($this->sugestoes, 'id');

        $consulta = Opcao::query()
            ->ativasDa(Lista::NomesDeMesa)
            ->where('categoria', $dados['categoria'])
            ->whereRaw('CHAR_LENGTH(rotulo) BETWEEN 1 AND 40')
            ->reorder()
            ->inRandomOrder();

        $sorteadas = (clone $consulta)
            ->whereNotIn('id', $anteriores)
            ->limit(10)
            ->get(['id', 'rotulo']);

        // Se a turma tiver poucas opções, completa sem duplicar nomes na mesma rodada.
        if ($sorteadas->count() < 10) {
            $restantes = (clone $consulta)
                ->whereNotIn('id', $sorteadas->modelKeys())
                ->limit(10 - $sorteadas->count())
                ->get(['id', 'rotulo']);

            $sorteadas = $sorteadas->concat($restantes);
        }

        $this->sugestoes = $sorteadas
            ->map(static fn (Opcao $opcao): array => [
                'id' => $opcao->id,
                'nome' => $opcao->rotulo,
            ])
            ->values()
            ->all();
    }

    public function selecionar(int $id): void
    {
        $this->resetErrorBag('sugestao');

        $oferecida = in_array($id, array_column($this->sugestoes, 'id'), true);

        $opcao = $oferecida
            ? Opcao::query()
                ->ativasDa(Lista::NomesDeMesa)
                ->where('categoria', $this->categoria)
                ->whereKey($id)
                ->first()
            : null;

        if ($opcao === null) {
            throw ValidationException::withMessages([
                'sugestao' => 'Esta sugestão não está mais disponível. Peça outras sugestões.',
            ]);
        }

        $this->nome = $opcao->rotulo;
    }

    public function render(): View
    {
        return view('livewire.mesa.formulario-criar-mesa', [
            'categorias' => Opcao::CATEGORIAS_NOMES_DE_MESA,
        ]);
    }
}
