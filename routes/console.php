<?php

use App\Acesso\RegistrosDeAcesso;
use App\Dominio\Jogos\Labirinto\SoloLabirinto;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prazos das partidas: também são processados ao carregar a mesa; o agendamento cobre quem não está olhando.
// Na hospedagem, um cron a cada minuto chama: php artisan schedule:run
Schedule::command('partidas:processar-prazos')->everyMinute()->withoutOverlapping();

Artisan::command('labirinto:limpar-tentativas', function (SoloLabirinto $solo) {
    $this->info($solo->limparExpiradas().' tentativas expiradas removidas.');
})->purpose('Apaga as jogadas solo abandonadas (abertas há mais de 30 minutos)');

Schedule::command('labirinto:limpar-tentativas')->everyTenMinutes()->withoutOverlapping();

Artisan::command('acessos:limpar-registros', function (RegistrosDeAcesso $registros) {
    $this->info($registros->limparExpirados().' registros de acesso expirados removidos.');
})->purpose('Apaga em lotes registros de acesso com mais de seis meses');

Schedule::command('acessos:limpar-registros')->daily()->withoutOverlapping();
