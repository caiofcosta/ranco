<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DismissaoDeModal extends Model
{
    protected $table = 'dismissoes_de_modal';

    protected $fillable = [
        'user_id',
        'modal_de_notificacao_id',
        'visualizou_em',
        'descartou_em',
        'nao_mostrar_novamente',
    ];

    protected function casts(): array
    {
        return [
            'visualizou_em' => 'datetime',
            'descartou_em' => 'datetime',
            'nao_mostrar_novamente' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function modal()
    {
        return $this->belongsTo(ModalDeNotificacao::class, 'modal_de_notificacao_id');
    }
}
