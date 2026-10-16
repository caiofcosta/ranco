<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Dominio\Plataforma\Plataforma;
use Illuminate\Console\Command;

final class ProcessarPrazos extends Command
{
    protected $signature = 'partidas:processar-prazos';

    protected $description = 'Pula as vezes cujo prazo venceu em todas as partidas em andamento';

    public function handle(Plataforma $plataforma): int
    {
        $verificadas = $plataforma->processarPrazosDeTodas();
        $this->info("Partidas verificadas: {$verificadas}");

        return self::SUCCESS;
    }
}
