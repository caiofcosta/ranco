<?php

namespace App\Providers;

use App\Actions\Fortify\ImpedirLoginDeContaBloqueada;
use App\Actions\Fortify\LimitarTentativasDeLogin;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Respostas\PedidoDeRecuperacaoRecebido;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configurarSequenciaDeLogin();
        $this->configurarRespostas();
    }

    /**
     * Sequência de login do Fortify com o limite de tentativas do RANÇO no lugar do limite padrão:
     * 5 erros por e-mail (ou 20 por IP) em 15 minutos levam a uma pausa de 15 minutos, e cada erro
     * traz uma provocação.
     */
    private function configurarSequenciaDeLogin(): void
    {
        Fortify::authenticateThrough(static fn (Request $request): array => array_filter([
            LimitarTentativasDeLogin::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            ImpedirLoginDeContaBloqueada::class,
            Features::enabled(Features::twoFactorAuthentication()) ? RedirectsIfTwoFactorAuthenticatable::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }

    /** A recuperação de senha responde igual para e-mails com e sem conta. */
    private function configurarRespostas(): void
    {
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PedidoDeRecuperacaoRecebido::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PedidoDeRecuperacaoRecebido::class);
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

    }
}
