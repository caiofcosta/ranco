<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Painel\AdministrarUsuario;
use App\Painel\DetalhesDoUsuario;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

class BuscarUsuario extends Page
{
    use WithPagination;

    protected static ?string $slug = 'usuarios/buscar';

    protected static ?string $title = 'Buscar usuário';

    protected static ?string $navigationLabel = 'Buscar usuário';

    protected static string|UnitEnum|null $navigationGroup = 'Usuários';

    protected string $view = 'filament.pages.buscar-usuario';

    public string $busca = '';

    #[Locked]
    public ?int $usuarioSelecionado = null;

    public bool $modalAberta = false;

    public static function canAccess(): bool
    {
        $usuario = auth()->user();

        return $usuario instanceof User && $usuario->canAccessPanel(Filament::getPanel('painel'));
    }

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function selecionar(int $id): void
    {
        User::query()->findOrFail($id);
        $this->usuarioSelecionado = $id;
        $this->modalAberta = true;
    }

    public function fecharModal(): void
    {
        $this->usuarioSelecionado = null;
        $this->modalAberta = false;
    }

    public function bloquearUsuario(AdministrarUsuario $administrar): void
    {
        $administrar->bloquear($this->administradorAtual(), $this->usuarioSelecionado());
        Notification::make()->title('Conta bloqueada; as sessões existentes foram encerradas.')->success()->send();
    }

    public function desbloquearUsuario(AdministrarUsuario $administrar): void
    {
        $administrar->desbloquear($this->administradorAtual(), $this->usuarioSelecionado());
        Notification::make()->title('Conta desbloqueada.')->success()->send();
    }

    public function excluirUsuario(AdministrarUsuario $administrar): void
    {
        $administrar->excluir($this->administradorAtual(), $this->usuarioSelecionado());
        $this->fecharModal();
        Notification::make()->title('Conta excluída. O histórico compartilhado foi preservado.')->success()->send();
    }

    public function updatingBusca(): void
    {
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $termo = trim($this->busca);
        $consulta = User::query();
        if ($termo !== '') {
            $consulta->where(static function ($consulta) use ($termo): void {
                $consulta->where('name', 'like', "%{$termo}%")
                    ->orWhere('email', 'like', "%{$termo}%");
            });
        }

        $usuarioSelecionado = $this->modalAberta && $this->usuarioSelecionado !== null
            ? User::query()->findOrFail($this->usuarioSelecionado)
            : null;

        return [
            'resultados' => $consulta
                ->orderBy('name')
                ->paginate(25),
            'detalhes' => $usuarioSelecionado === null
                ? null
                : app(DetalhesDoUsuario::class)->consultar($usuarioSelecionado),
            'podeAdministrarUsuario' => $usuarioSelecionado !== null
                && ! $usuarioSelecionado->is(auth()->user())
                && ! in_array(mb_strtolower($usuarioSelecionado->email), array_map('mb_strtolower', config('ranco.administradores', [])), true),
        ];
    }

    private function administradorAtual(): User
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }

    private function usuarioSelecionado(): int
    {
        abort_unless($this->usuarioSelecionado !== null, 404);

        return $this->usuarioSelecionado;
    }
}
