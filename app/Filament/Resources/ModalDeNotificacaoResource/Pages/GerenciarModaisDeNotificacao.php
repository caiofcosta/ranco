<?php

namespace App\Filament\Resources\ModalDeNotificacaoResource\Pages;

use App\Filament\Resources\ModalDeNotificacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarModaisDeNotificacao extends ManageRecords
{
    protected static string $resource = ModalDeNotificacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
