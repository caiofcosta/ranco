<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    public string $confirmacao = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $usuario = Auth::user();

        if ($usuario->temSenha()) {
            $this->validate([
                'password' => $this->currentPasswordRules(),
            ]);
        } else {
            $this->validate([
                'confirmacao' => ['required', 'string'],
            ]);

            if (mb_strtolower(trim($this->confirmacao)) !== mb_strtolower($usuario->email)) {
                throw ValidationException::withMessages([
                    'confirmacao' => __('The email does not match your account.'),
                ]);
            }
        }

        tap($usuario, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

            <flux:subheading>
                Ao excluir sua conta, apagamos seus dados de conta e seus aceites. Os registros de acesso (IP, data e hora) ficam guardados por 6 meses, e as mesas e partidas de que você participou continuam no histórico dos outros participantes, inclusive com seu nome. Detalhes na <a href="{{ route('documentos.privacidade') }}" class="underline" target="_blank" rel="noopener">Política de Privacidade</a>.
            </flux:subheading>
            <flux:subheading class="mt-3">
                @if (auth()->user()->temSenha())
                    Digite sua senha para confirmar.
                @else
                    Digite seu e-mail para confirmar.
                @endif
            </flux:subheading>
        </div>

        @if (auth()->user()->temSenha())
            <flux:input wire:model="password" :label="__('Password')" type="password" viewable />
        @else
            <flux:input wire:model="confirmacao" :label="__('Email')" type="email" autocomplete="username" required />
        @endif

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
