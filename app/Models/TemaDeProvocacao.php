<?php

declare(strict_types=1);

namespace App\Models;

use App\Provocacao\TipoDeTema;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tema da tabela `temas_de_provocacao`. O código não muda depois de criado.
 *
 * @property string $codigo
 * @property TipoDeTema $tipo
 * @property string $significado
 */
#[Fillable(['codigo', 'tipo', 'significado'])]
class TemaDeProvocacao extends Model
{
    protected $table = 'temas_de_provocacao';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDeTema::class,
        ];
    }

    /** @return BelongsToMany<FraseDeProvocacao, $this> */
    public function frases(): BelongsToMany
    {
        return $this->belongsToMany(
            FraseDeProvocacao::class,
            'provocacoes_temas',
            'tema',
            'provocacao_id',
            'codigo',
            'id',
        );
    }

    /** @return BelongsToMany<MomentoDeProvocacao, $this> */
    public function momentos(): BelongsToMany
    {
        return $this->belongsToMany(
            MomentoDeProvocacao::class,
            'momentos_temas',
            'tema',
            'momento',
            'codigo',
            'codigo',
        );
    }
}
