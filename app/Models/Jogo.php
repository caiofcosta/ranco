<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado administrativo de disponibilidade de um jogo do catálogo do sistema.
 *
 * @property string $id
 * @property bool $ativo
 */
#[Fillable(['ativo'])]
class Jogo extends Model
{
    protected $table = 'jogos';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
