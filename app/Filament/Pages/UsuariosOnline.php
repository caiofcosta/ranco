<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class UsuariosOnline extends Page
{
    public const JANELA_MINUTOS = 5;

    protected static ?string $slug = 'usuarios/online';

    protected static ?string $title = 'Usuários online';

    protected static ?string $navigationLabel = 'Online agora';

    protected static string|UnitEnum|null $navigationGroup = 'Usuários';

    protected string $view = 'filament.pages.usuarios-online';

    public static function canAccess(): bool
    {
        $usuario = auth()->user();

        return $usuario instanceof User && $usuario->canAccessPanel(Filament::getPanel('painel'));
    }

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $limite = now()->timestamp - (self::JANELA_MINUTOS * 60);

        $usuarios = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->whereNotNull('sessions.user_id')
            ->where('sessions.last_activity', '>=', $limite)
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('MAX(sessions.last_activity) as ultima_atividade')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('ultima_atividade')
            ->paginate(25);

        return ['usuarios' => $usuarios];
    }
}
