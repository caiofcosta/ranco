<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Convite de amizade entre contas, sem envio de e-mail.
 *
 * @property int $id
 * @property string $de
 * @property int|null $para
 * @property Carbon $expira_em
 * @property Carbon|null $aceito_em
 * @property string|null $aceito_por
 * @property Carbon|null $recusado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['de', 'para', 'expira_em'])]
class ConviteDeAmizade extends Model
{
    protected $table = 'convites_de_amizade';

    protected function casts(): array
    {
        return [
            'para' => 'integer',
            'expira_em' => 'datetime',
            'aceito_em' => 'datetime',
            'recusado_em' => 'datetime',
        ];
    }

    public function aberto(): bool
    {
        return $this->para !== null && $this->aceito_em === null
            && $this->recusado_em === null && $this->expira_em->isFuture();
    }
}
