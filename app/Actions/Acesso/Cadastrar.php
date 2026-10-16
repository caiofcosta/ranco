<?php

declare(strict_types=1);

namespace App\Actions\Acesso;

use App\Acesso\LinksDeEntrada;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Cadastro que não revela quem tem conta. E-mail novo: cria a conta e manda o link de confirmação.
 * E-mail que já tem conta: não cria nem altera nada e manda um link de acesso. Quem pediu vê a
 * mesma resposta nos dois casos.
 */
final class Cadastrar
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    public function __construct(private readonly LinksDeEntrada $links)
    {
    }

    /**
     * @param array<string, mixed> $dados
     * @return bool Se a conta foi criada agora.
     */
    public function executar(array $dados): bool
    {
        $validos = Validator::make($dados, [
            'name' => $this->nameRules(),
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        $email = mb_strtolower(trim((string) $validos['email']));
        $existente = User::where('email', $email)->first();

        if ($existente !== null) {
            // O hash é calculado mesmo sem uso, para os dois casos levarem o mesmo tempo.
            Hash::make((string) $validos['password']);
            $this->links->enviarAcesso($existente, pediuCadastro: true);

            return false;
        }

        $usuario = User::create([
            'name' => (string) $validos['name'],
            'email' => $email,
            'password' => (string) $validos['password'],
        ]);
        event(new Registered($usuario));

        return true;
    }
}
