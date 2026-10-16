<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use Illuminate\View\View;

/** Abre o formulário; o componente Livewire oferece os nomes por turma. */
final class FormularioCriarMesaController
{
    public function __invoke(): View
    {
        return view('mesa.formulario');
    }
}
