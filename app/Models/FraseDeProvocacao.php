<?php

declare(strict_types=1);

namespace App\Models;

use App\Dominio\Provocacao\Perspectiva;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Frase da tabela `provocacoes`. O domínio já tem a classe `Provocacao`; por isso o model
 * Eloquent usa outro nome. Para tirar do ar, desativa-se: não há exclusão.
 *
 * @property int $id
 * @property string $texto
 * @property int $intensidade
 * @property Perspectiva $perspectiva
 * @property bool $ativa
 */
#[Fillable(['texto', 'intensidade', 'perspectiva', 'ativa'])]
class FraseDeProvocacao extends Model
{
    protected $table = 'provocacoes';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'intensidade' => 'integer',
            'perspectiva' => Perspectiva::class,
            'ativa' => 'boolean',
        ];
    }

    /** @return BelongsToMany<TemaDeProvocacao, $this> */
    public function temas(): BelongsToMany
    {
        return $this->belongsToMany(
            TemaDeProvocacao::class,
            'provocacoes_temas',
            'provocacao_id',
            'tema',
            'id',
            'codigo',
        );
    }

    /** @return list<string> Variáveis usadas no texto, como "nome" em "{nome}". */
    public function variaveisDoTexto(): array
    {
        preg_match_all('/\{(\w+)\}/u', $this->texto, $achados);

        return array_values(array_unique($achados[1]));
    }
}
