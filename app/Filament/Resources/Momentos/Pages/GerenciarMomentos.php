<?php

declare(strict_types=1);

namespace App\Filament\Resources\Momentos\Pages;

use App\Filament\Resources\Momentos\MomentoDeProvocacaoResource;
use Filament\Resources\Pages\ManageRecords;

class GerenciarMomentos extends ManageRecords
{
    protected static string $resource = MomentoDeProvocacaoResource::class;
}
