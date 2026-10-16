<?php

declare(strict_types=1);

namespace App\Actions\Acesso;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as ContaGoogle;

/**
 * Encontra ou cria a conta de quem entrou com o Google. O mesmo e-mail verificado é sempre a mesma
 * conta, qualquer que seja o método de login.
 */
final class EntrarComGoogle
{
    public function executar(ContaGoogle $conta): User
    {
        $dados = method_exists($conta, 'getRaw') ? $conta->getRaw() : [];
        $verificado = filter_var($dados['email_verified'] ?? $dados['verified_email'] ?? false, FILTER_VALIDATE_BOOL);
        $email = mb_strtolower(trim((string) $conta->getEmail()));

        if (! $verificado || $email === '') {
            throw ValidationException::withMessages(['email' => 'O e-mail da sua conta Google não está verificado.']);
        }

        return DB::transaction(static function () use ($conta, $email): User {
            $usuario = User::where('google_id', $conta->getId())->first()
                ?? User::where('email', $email)->first()
                ?? new User(['name' => $conta->getName() ?: Str::before($email, '@'), 'email' => $email]);

            if ($usuario->bloqueado_em !== null) {
                throw ValidationException::withMessages(['email' => 'Não foi possível entrar nesta conta.']);
            }

            if (! $usuario->exists || ! $usuario->hasVerifiedEmail()) {
                // Conta nova, ou cadastrada antes sem confirmar o e-mail: a senha pode ter sido
                // definida por outra pessoa, então é descartada.
                $usuario->password = null;
            }

            $usuario->google_id ??= (string) $conta->getId();
            $foto = $conta->getAvatar();
            $usuario->google_avatar_url = is_string($foto)
                && filter_var($foto, FILTER_VALIDATE_URL)
                && parse_url($foto, PHP_URL_SCHEME) === 'https' ? $foto : null;
            $usuario->email_verified_at ??= now();
            $usuario->save();

            return $usuario;
        });
    }
}
