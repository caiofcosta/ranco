<?php

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public bool $invisivel = false;
    public bool $jogando = false;

    public function mount(Plataforma $plataforma): void
    {
        $eu = Auth::user()->identificadorNoDominio();
        $this->invisivel = $plataforma->estaInvisivel($eu);
        $this->jogando = $plataforma->temJogadaEmAndamento($eu);
    }

    public function updatedInvisivel(bool $valor): void
    {
        $plataforma = app(Plataforma::class);
        $eu = Auth::user()->identificadorNoDominio();
        try {
            $valor ? $plataforma->ficarInvisivel($eu) : $plataforma->ficarVisivel($eu);
        } catch (RegraViolada $erro) {
            $this->invisivel = false;
            $this->jogando = true;
            $this->addError('invisivel', $erro->getMessage());
        }
    }
}; ?>

<section class="mt-10 space-y-4" data-test="visibilidade">
    <div>
        <flux:heading>Visibilidade</flux:heading>
        <flux:subheading>Invisível, você aparece offline e não joga nem convida ninguém.</flux:subheading>
    </div>

    @if ($jogando && ! $invisivel)
        <p class="text-sm text-muted">Termine sua jogada e saia da mesa para ficar invisível.</p>
    @endif

    <flux:switch wire:model.live="invisivel" label="Ficar invisível" :disabled="$jogando && ! $invisivel" data-test="ficar-invisivel" />

    @error('invisivel')
        <p class="text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror
</section>
