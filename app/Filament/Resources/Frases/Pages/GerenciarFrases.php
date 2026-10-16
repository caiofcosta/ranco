<?php

declare(strict_types=1);

namespace App\Filament\Resources\Frases\Pages;

use App\Filament\Resources\Frases\FraseDeProvocacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarFrases extends ManageRecords
{
    protected static string $resource = FraseDeProvocacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
