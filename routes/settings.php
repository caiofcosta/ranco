<?php

use App\Http\Controllers\Acesso\RegrasAceitasController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/regras-aceitas', RegrasAceitasController::class)->name('settings.regras-aceitas');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'confirma.senha.se.tiver',
        ])
        ->name('security.edit');
});
