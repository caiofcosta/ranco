<?php

declare(strict_types=1);

namespace App\Filament\Resources\Opcoes\Pages;

use App\Filament\Resources\Opcoes\OpcaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarOpcoes extends ManageRecords
{
    protected static string $resource = OpcaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
