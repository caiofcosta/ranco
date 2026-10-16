<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Dominio\Relogio;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Suporte\LacraOTempo;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class MapasDaRodadaTest extends TestCase
{
    use LacraOTempo;
    use RefreshDatabase;

    public function test_sequencial_preserva_o_sorteio_no_reload_e_no_assistir_e_troca_ao_fechar_rodada(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        [$mesa, $ana, $bruno] = $this->mesa();
        $p = app(Plataforma::class);
        $p->iniciarPartida($mesa->id, (string) $ana->id);
        $mapa = $p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto();
        $dados = $this->iniciar($mesa, $ana);
        $this->get(route('mesas.jogar', $mesa->id))->assertViewHas('mapaDaRodada', $mapa)->assertSee($mapa['nome']);
        $this->actingAs($bruno)->get(route('mesas.assistir', $mesa->id))->assertViewHas('mapaDaRodada', $mapa)->assertSee('data-definicao', false);
        $this->actingAs($ana)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 1]))->assertUnprocessable();
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dados, 'tempo' => 20000]))->assertOk();
        $dadosBruno = $this->iniciar($mesa, $bruno);
        $this->get(route('mesas.jogar', $mesa->id))->assertViewHas('mapaDaRodada', $mapa);
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dadosBruno, 'tempo' => 22000]))->assertOk();
        $partida = $p->mesa($mesa->id)->partidaAtiva();
        self::assertSame(2, $partida->rodadaAtual());
        self::assertNotSame($mapa['id'], $partida->mapaLabirinto()['id']);
        self::assertSame($mapa, $partida->mapaLabirinto(1));
        // Reenvio legítimo identifica a rodada original mesmo depois da troca de mapa.
        $this->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$dadosBruno, 'tempo' => 22000]))->assertOk();
        self::assertSame(0, DB::table('tentativas_solo_labirinto')->count());
    }

    public function test_paralelo_compartilha_mapa_e_congela_versoes_mesmo_apos_desativacao_e_nova_publicacao(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        $ids = DB::table('mapas_labirinto')->orderBy('id')->pluck('id')->all();
        $dois = array_slice($ids, 0, 2);
        DB::table('mapas_labirinto')->whereNotIn('id', $dois)->update(['ativo' => false]);
        [$mesa, $ana, $bruno] = $this->mesa(ModoExecucao::Paralelo);
        $p = app(Plataforma::class);
        $p->iniciarPartida($mesa->id, (string) $ana->id);
        $primeiro = $p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto();
        $a = $this->iniciar($mesa, $ana);
        $b = $this->iniciar($mesa, $bruno);
        $this->get(route('mesas.jogar', $mesa->id))->assertViewHas('mapaDaRodada', $primeiro);
        $outro = DB::table('versoes_labirinto')->whereIn('mapa_id', $dois)->where('id', '<>', $primeiro['id'])->first();
        // Publicação posterior não muda nem inclui versões no conjunto da partida.
        DB::table('versoes_labirinto')->insert([
            'mapa_id' => $outro->mapa_id, 'numero' => 2, 'nome' => 'Versão posterior',
            'desenho' => $outro->desenho, 'publicada_em' => now(),
        ]);
        DB::table('mapas_labirinto')->where('id', $outro->mapa_id)->update(['versao_publicada' => 2]);
        DB::table('mapas_labirinto')->whereIn('id', $dois)->update(['ativo' => false]);
        DB::table('mapas_labirinto')->where('id', $ids[2])->update(['ativo' => true]);
        $this->actingAs($ana)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$a, 'tempo' => 20000]))->assertOk();
        $this->actingAs($bruno)->postJson(route('mesas.jogar.resultado', $mesa->id), $this->lacrado([...$b, 'tempo' => 22000]))->assertOk();
        $segundo = $p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto();
        self::assertSame((int) $outro->id, $segundo['id']);
        self::assertSame(json_decode($outro->desenho, true), $segundo['definicao']);
    }

    public function test_sem_catalogo_nao_inicia_e_transacao_preserva_configuracao(): void
    {
        [$mesa, $ana] = $this->mesa();
        try {
            app(Plataforma::class)->iniciarPartida($mesa->id, (string) $ana->id);
            self::fail('Deveria impedir início sem mapa.');
        } catch (RegraViolada $erro) {
            self::assertStringContainsString('ao menos um mapa', $erro->getMessage());
        }
        self::assertSame(SituacaoPartida::EmConfiguracao, app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->situacao());
    }

    public function test_partida_legada_mantem_rodada_aberta_e_adota_catalogo_na_seguinte_inclusive_pulo(): void
    {
        [$mesa, $ana] = $this->mesa();
        $p = app(Plataforma::class);
        $p->naMesa($mesa->id, fn (Mesa $m) => $m->iniciarPartida((string) $ana->id));
        $this->seed(MapasLabirintoSeeder::class);
        self::assertNull($p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto());
        for ($i = 0; $i < 2; $i++) {
            $vez = $p->mesa($mesa->id)->partidaAtiva()->vezAtual();
            $p->pularVez($mesa->id, $vez, (string) $ana->id);
        }
        $partida = $p->mesa($mesa->id)->partidaAtiva();
        self::assertSame(2, $partida->rodadaAtual());
        self::assertNotNull($partida->mapaLabirinto());
        self::assertNull($partida->mapaLabirinto(1));
    }

    public function test_local_recebe_mesmo_mapa_e_rodada_seguinte_muda(): void
    {
        $this->seed(MapasLabirintoSeeder::class);
        [$mesa, $ana, $bruno] = $this->mesa();
        $p = app(Plataforma::class);
        $p->naMesa($mesa->id, function (Mesa $m) use ($ana, $bruno): void {
            $local = $m->cadastrarParticipanteLocal('Local', 'local@example.test', (string) $ana->id);
            $m->configurarLugares(3, (string) $ana->id);
            $m->partidaAtiva()->adicionarParticipante($local);
            $m->partidaAtiva()->reordenar([$local, (string) $ana->id, (string) $bruno->id], (string) $ana->id);
        });
        $p->iniciarPartida($mesa->id, (string) $ana->id);
        $mapa = $p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto();
        $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertSee('Jogar pela vez de Local')->assertViewHas('mapaDaRodada', $mapa);
        for ($i = 0; $i < 3; $i++) {
            $p->pularVez($mesa->id, $p->mesa($mesa->id)->partidaAtiva()->vezAtual(), (string) $ana->id);
        }
        self::assertNotSame($mapa['id'], $p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto()['id']);
    }

    public function test_prazo_paralelo_troca_mapa_sob_a_mesma_persistencia_da_rodada(): void
    {
        $relogio = new RelogioFalso(new \DateTimeImmutable('2026-10-05 12:00:00'));
        $this->app->instance(Relogio::class, $relogio);
        $this->seed(MapasLabirintoSeeder::class);
        [$mesa, $ana] = $this->mesa(ModoExecucao::Paralelo);
        $p = app(Plataforma::class);
        $p->iniciarPartida($mesa->id, (string) $ana->id);
        $mapa = $p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto();
        $relogio->avancar(1801);
        $partida = $p->mesa($mesa->id)->partidaAtiva();
        self::assertSame(2, $partida->rodadaAtual());
        self::assertNotSame($mapa['id'], $partida->mapaLabirinto()['id']);
        self::assertSame($partida->mapaLabirinto(), $p->mesa($mesa->id)->partidaAtiva()->mapaLabirinto());
    }

    private function iniciar(Mesa $mesa, User $pessoa): array
    {
        $pagina = $this->actingAs($pessoa)->get(route('mesas.jogar', $mesa->id))->assertOk();
        $dados = collect(['jogada', 'partida', 'rodada', 'desempate'])->mapWithKeys(fn ($c) => [$c => $pagina->viewData($c)])->all();
        $this->post(route('mesas.jogar.iniciar', $mesa->id), $dados)->assertRedirect();

        return $dados;
    }

    private function mesa(ModoExecucao $modo = ModoExecucao::Sequencial): array
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        $p = app(Plataforma::class);
        $mesa = $p->criarMesa((string) $ana->id);
        $p->naMesa($mesa->id, function (Mesa $m) use ($ana, $bruno, $modo): void {
            $m->adicionarMembro((string) $bruno->id);
            $m->novaPartida(CatalogoDeJogos::configuracao('labirinto', 1800, null, 20, $modo), (string) $ana->id);
        });

        return [$mesa, $ana, $bruno];
    }
}
