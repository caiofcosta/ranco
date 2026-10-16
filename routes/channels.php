<?php

use App\Mesa\Tempo\MesaAoVivo;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('mesa.{id}', fn ($usuario, string $id) => MesaAoVivo::autorizada($usuario, $id) !== null);

Broadcast::channel('presenca.mesa.{id}', function ($usuario, string $id): ?array {
    if (! MesaAoVivo::presencaAutorizada($usuario, $id)) {
        return null;
    }

    return [
        'id' => $usuario->identificadorNoDominio(),
        'nome' => $usuario->name,
        'iniciais' => mb_strtoupper(mb_substr($usuario->name, 0, 1)),
    ];
});
