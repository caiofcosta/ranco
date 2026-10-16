<?php

declare(strict_types=1);

namespace App\Filament\Resources\Temas\Pages;

use App\Filament\Resources\Temas\TemaDeProvocacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarTemas extends ManageRecords
{
    protected static string $resource = TemaDeProvocacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
