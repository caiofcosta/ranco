<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jogos\JogosDisponiveis as CatalogoDisponivel;
use App\Models\Jogo;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class DisponibilidadeDosJogos extends Page
{
    protected static ?string $slug = 'jogos/disponibilidade';

    protected static ?string $title = 'Disponibilidade dos jogos';

    protected static ?string $navigationLabel = 'Disponibilidade';

    protected static string|UnitEnum|null $navigationGroup = 'Jogos';

    protected string $view = 'filament.pages.disponibilidade-dos-jogos';

    public static function canAccess(): bool
    {
        $usuario = auth()->user();

        return $usuario instanceof User && $usuario->canAccessPanel(Filament::getPanel('painel'));
    }

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function alternar(string $id): void
    {
        $jogo = Jogo::query()->findOrFail($id);
        $jogo->ativo = ! $jogo->ativo;
        $jogo->save();

        Notification::make()
            ->title($jogo->ativo ? 'Jogo ativado.' : 'Jogo inativado.')
            ->success()
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['jogos' => app(CatalogoDisponivel::class)->todos()];
    }
}
