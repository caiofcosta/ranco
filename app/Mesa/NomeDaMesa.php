<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Models\User;

/** Nome de exibição: o gravado ou, se vazio, "Mesa de {nome do admin}". */
final class NomeDaMesa
{
    public static function de(Mesa $mesa): string
    {
        if ($mesa->nome() !== '') {
            return $mesa->nome();
        }

        $admin = User::query()->find($mesa->admin())?->name;

        return is_string($admin) && $admin !== '' ? 'Mesa de '.$admin : 'Mesa';
    }
}
