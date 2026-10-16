<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $versao
 * @property CarbonImmutable $aceito_em
 * @property string $ip
 */
#[Fillable(['user_id', 'versao', 'aceito_em', 'ip'])]
final class Aceite extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['aceito_em' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
