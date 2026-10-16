<?php

namespace Database\Factories;

use App\Acesso\Aceites;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(static function (User $usuario): void {
            if (app()->runningUnitTests() && config('testes.aceite_automatico', true)) {
                app(Aceites::class)->registrar($usuario, '127.0.0.1');
            }
        });
    }

    public function aceitou(): static
    {
        return $this->afterCreating(static function (User $usuario): void {
            app(Aceites::class)->registrar($usuario, '127.0.0.1');
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Conta que entrou por Google ou pelo link: sem senha. */
    public function semSenha(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static {}
}
