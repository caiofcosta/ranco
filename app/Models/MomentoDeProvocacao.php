<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Momento da tabela `momentos_de_provocacao`. O código e as variáveis vêm do enum do domínio;
 * o Painel só edita a descrição e os temas aceitos.
 *
 * @property string $codigo
 * @property string $descricao
 * @property string $variaveis
 * @property string|null $jogo
 */
#[Fillable(['descricao'])]
class MomentoDeProvocacao extends Model
{
    protected $table = 'momentos_de_provocacao';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @return BelongsToMany<TemaDeProvocacao, $this> */
    public function temas(): BelongsToMany
    {
        return $this->belongsToMany(
            TemaDeProvocacao::class,
            'momentos_temas',
            'momento',
            'tema',
            'codigo',
            'codigo',
        );
    }

    /** @return list<string> */
    public function variaveisOferecidas(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $nome): string => trim($nome),
            explode(',', $this->variaveis),
        )));
    }

    /** Frases ativas cujo tema o momento aceita e cujas variáveis ele oferece. */
    public function frasesUtilizaveis(): int
    {
        $oferecidas = $this->variaveisOferecidas();
        $vistas = [];
        $conta = 0;

        foreach ($this->temas as $tema) {
            foreach ($tema->frases as $frase) {
                if (isset($vistas[$frase->id]) || ! $frase->ativa) {
                    continue;
                }

                $vistas[$frase->id] = true;

                if (array_diff($frase->variaveisDoTexto(), $oferecidas) === []) {
                    $conta++;
                }
            }
        }

        return $conta;
    }
}
