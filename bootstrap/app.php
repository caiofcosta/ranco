<?php

use App\Http\Middleware\ConfirmaSenhaSeTiver;
use App\Http\Middleware\ExigeAceiteDasRegras;
use App\Http\Middleware\ImpedirAcessoDeContaBloqueada;
use App\Http\Middleware\RegistraAcesso;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [
            RegistraAcesso::class,
            ImpedirAcessoDeContaBloqueada::class,
            ExigeAceiteDasRegras::class,
        ]);
        $middleware->alias([
            'confirma.senha.se.tiver' => ConfirmaSenhaSeTiver::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
