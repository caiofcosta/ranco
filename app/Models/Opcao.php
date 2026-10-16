<?php

declare(strict_types=1);

namespace App\Models;

use App\Opcoes\Lista;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Opção de uma lista editável no Painel: o `valor` é o que o sistema guarda e não muda depois de
 * criado; o `rotulo` é o que a pessoa vê. Para tirar uma opção, desativa-se em vez de apagar.
 *
 * @property int $id
 * @property Lista $lista
 * @property string $categoria
 * @property string $valor
 * @property string $rotulo
 * @property int $ordem
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lista', 'categoria', 'valor', 'rotulo', 'ordem', 'ativo'])]
class Opcao extends Model
{
    public const CATEGORIAS_NOMES_DE_MESA = [
        'familia' => 'Família',
        'amigos' => 'Amigos',
        'casal' => 'Casal',
        'empresa' => 'Empresa',
        'faculdade' => 'Faculdade',
        'generico' => 'Genérico',
    ];

    protected $table = 'opcoes';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lista' => Lista::class,
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Opções ativas de uma lista, na ordem de exibição.
     *
     * @param Builder<Opcao> $consulta
     */
    public function scopeAtivasDa(Builder $consulta, Lista $lista): void
    {
        $consulta->where('lista', $lista->value)->where('ativo', true)->orderBy('ordem')->orderBy('rotulo');
    }
}
