<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModalDeNotificacao extends Model
{
    protected $table = 'modais_de_notificacao';

    protected $fillable = [
        'titulo',
        'conteudo',
        'tipo',
        'ativo',
        'data_inicio',
        'data_fim',
    ];

    protected function casts(): array
    {
        return [
            'conteudo' => 'array',
            'ativo' => 'boolean',
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
        ];
    }

    public function dismissoes()
    {
        return $this->hasMany(DismissaoDeModal::class);
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true)
            ->where(function ($q) {
                $q->whereNull('data_inicio')->orWhere('data_inicio', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('data_fim')->orWhere('data_fim', '>=', now());
            });
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
