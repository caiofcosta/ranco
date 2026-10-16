<?php

namespace App\Providers;

use App\Http\Middleware\ExigeAceiteDasRegras;
use App\Models\DismissaoDeModal;
use App\Models\ModalDeNotificacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->authorizeViewPulse();
        $this->compartilharAvisosNaoLidos();
        Livewire::addPersistentMiddleware([ExigeAceiteDasRegras::class]);
    }

    /**
     * Authorize access to Laravel Pulse dashboard using existing admin rule.
     */
    protected function authorizeViewPulse(): void
    {
        Gate::define('viewPulse', function ($user) {
            return $user?->canAccessPanel(\Filament\Facades\Filament::getPanel('painel')) ?? false;
        });
    }

    protected function compartilharAvisosNaoLidos(): void
    {
        View::composer('layouts.app.sidebar', function ($view): void {
            if (! auth()->check()) {
                $view->with('naoLidasNoMenu', 0);

                return;
            }

            $idsAtivos = ModalDeNotificacao::ativas()->pluck('id');

            if ($idsAtivos->isEmpty()) {
                $view->with('naoLidasNoMenu', 0);

                return;
            }

            $lidas = DismissaoDeModal::where('user_id', auth()->id())
                ->whereIn('modal_de_notificacao_id', $idsAtivos)
                ->whereNotNull('visualizou_em')
                ->count();

            $view->with('naoLidasNoMenu', $idsAtivos->count() - $lidas);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Senha: mínimo de 8 caracteres e, em produção, fora das listas de senhas vazadas
        // (consulta ao Have I Been Pwned). Sem exigir símbolos ou maiúsculas, para não afastar quem só quer jogar.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(8)->uncompromised()
            : Password::min(8),
        );
    }
}
