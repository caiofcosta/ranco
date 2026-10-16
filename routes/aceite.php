<?php

use App\Http\Controllers\Acesso\AceiteController;
use App\Http\Controllers\Acesso\DocumentosController;
use Illuminate\Support\Facades\Route;

foreach (['termos', 'privacidade', 'conduta'] as $documento) {
    Route::get($documento.'/{versao?}', DocumentosController::class)
        ->defaults('documento', $documento)
        ->where('versao', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->name('documentos.'.$documento);
}

Route::middleware('auth')->group(function () {
    Route::get('aceite', [AceiteController::class, 'mostrar'])->name('aceite.mostrar');
    Route::post('aceite', [AceiteController::class, 'registrar'])->name('aceite.registrar');
    Route::view('conta/excluir', 'conta.excluir')->name('conta.excluir');
});
