<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LimpezaTentativasLabirintoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function limpa_lotes_expirados_preservando_limite_ativas_resultados_e_contas(): void
    {
        $this->freezeTime();
        $this->seed(MapasLabirintoSeeder::class);
        $pessoa = User::factory()->create();
        $versao = DB::table('versoes_labirinto')->value('id');
        $limite = \App\Dominio\Jogos\Labirinto\TentativaSolo::VALIDADE_SEGUNDOS;
        $dados = ['pessoa_id' => $pessoa->id, 'versao_id' => $versao, 'iniciada_em' => now()->subSeconds($limite + 60)];
        $lote = [];
        for ($i = 0; $i < 1001; $i++) {
            $lote[] = ['id' => (string) Str::uuid(), ...$dados];
        }
        DB::table('tentativas_solo_labirinto')->insert($lote);
        foreach ([now()->subSeconds($limite), now()] as $inicio) {
            DB::table('tentativas_solo_labirinto')->insert(['id' => (string) Str::uuid(), ...$dados, 'iniciada_em' => $inicio]);
        }
        $concluida = (string) Str::uuid();
        DB::table('tentativas_solo_labirinto')->insert(['id' => $concluida, ...$dados, 'tempo' => 15000, 'recorde' => true, 'concluida_em' => now()->subSeconds($limite)]);
        $this->artisan('labirinto:limpar-tentativas')->expectsOutput('1001 tentativas expiradas removidas.')->assertSuccessful();
        $this->assertDatabaseCount('tentativas_solo_labirinto', 3);
        $this->assertDatabaseHas('tentativas_solo_labirinto', ['id' => $concluida, 'tempo' => 15000, 'recorde' => true]);
        $this->assertDatabaseHas('users', ['id' => $pessoa->id]);
        $this->artisan('labirinto:limpar-tentativas')->expectsOutput('0 tentativas expiradas removidas.')->assertSuccessful();
    }

    #[Test]
    public function agendadorRodaALimpezaACadaDezMinutosEOsPrazosACadaMinuto(): void
    {
        $agenda = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->mapWithKeys(fn ($evento): array => [trim(\Illuminate\Support\Str::after($evento->command, 'artisan')," '\"") => $evento->expression]);

        $this->assertSame('*/10 * * * *', $agenda['labirinto:limpar-tentativas']);
        $this->assertSame('* * * * *', $agenda['partidas:processar-prazos']);
    }
}
