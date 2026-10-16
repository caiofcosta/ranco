<?php

namespace App\Models;

use App\Acesso\LinksDeEntrada;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password Nula em conta que entrou só por link ou Google.
 * @property string|null $google_id
 * @property string|null $google_avatar_url
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property CarbonImmutable|null $bloqueado_em
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'bloqueado_em' => 'datetime',
        ];
    }

    /** O link de confirmação do RANÇO também entra na conta, para funcionar em qualquer aparelho. */
    public function sendEmailVerificationNotification(): void
    {
        app(LinksDeEntrada::class)->enviarConfirmacao($this);
    }

    /** Só os administradores do sistema (config ranco.administradores) entram no Painel. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasVerifiedEmail()
            && $this->bloqueado_em === null
            && in_array(mb_strtolower($this->email), config('ranco.administradores'), true);
    }

    /** @return HasMany<Aceite, $this> */
    public function aceites(): HasMany
    {
        return $this->hasMany(Aceite::class);
    }

    /** Identificador desta conta no domínio: mesas, convites e partidas usam essa string. */
    public function identificadorNoDominio(): string
    {
        return (string) $this->id;
    }

    /** Conta que entrou só por Google ou link ainda não tem senha. */
    public function temSenha(): bool
    {
        return $this->password !== null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1));
    }
}
