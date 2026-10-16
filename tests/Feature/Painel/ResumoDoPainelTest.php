<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Relogio;
use App\Models\User;
use App\Painel\ResumoDoPainel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class ResumoDoPainelTest extends TestCase
{
    use RefreshDatabase;

    /** Atualizada há 30 minutos (agora é 14:00Z): passa do limite de 15 minutos. */
    private const INATIVA = ['atualizada_em' => '2026-10-07 13:30:00.000'];

    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        parent::setUp();

        // 14:00 UTC é 11:00 em Brasília. O dia 07/10 de Brasília vai de 07/10 03:00Z até 08/10 03:00Z.
        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-07T14:00:00+00:00'));
        $this->app->instance(Relogio::class, $this->relogio);
    }

    private function resumo(): ResumoDoPainel
    {
        return app(ResumoDoPainel::class);
    }

    private function irPara(string $instante): void
    {
        $segundos = (new DateTimeImmutable($instante))->getTimestamp() - $this->relogio->agora()->getTimestamp();
        $this->assertGreaterThan(0, $segundos, 'O relógio do teste só anda para frente.');
        $this->relogio->avancar($segundos);
    }

    /** @param array<string, mixed> $campos */
    private function usuario(array $campos = []): User
    {
        return User::factory()->create($campos);
    }

    private function visto(User $usuario, ?string $visto): User
    {
        DB::table('users')->where('id', $usuario->id)->update(['visto_em' => $visto]);

        return $usuario;
    }

    /**
     * Linha direta em `mesas`: o painel só lê colunas, então o `estado` pode ser qualquer coisa.
     *
     * @param array<string, mixed> $campos
     */
    private function mesa(array $campos = []): string
    {
        $id = bin2hex(random_bytes(8));
        DB::table('mesas')->insert([
            'id' => $id,
            'admin' => '0',
            'situacao' => 'aberta',
            'partida_em_andamento' => 0,
            'estado' => 'não é lido pelo painel',
            'versao' => 1,
            'atualizada_em' => '2026-10-07 13:55:00.000',
            ...$campos,
        ]);

        return $id;
    }

    private function membro(string $mesaId, string $usuario, ?string $ativo, string $situacao = 'ativa'): void
    {
        DB::table('membros_mesa')->insert([
            'mesa_id' => $mesaId,
            'usuario' => $usuario,
            'situacao' => $situacao,
            'usuario_ativo' => $ativo,
        ]);
    }

    // Usuários

    #[Test]
    public function contaTodosOsUsuariosInclusiveAdministradorEEmailNaoVerificado(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->usuario();
        $this->usuario(['email' => 'admin@exemplo.com']);
        $this->usuario(['email_verified_at' => null]);

        $this->assertSame(3, $this->resumo()->usuariosCadastrados());
    }

    #[Test]
    public function novosHojeContamSoOsCriadosNoDiaDeSaoPaulo(): void
    {
        $this->usuario(['created_at' => '2026-10-07 02:59:59']); // 06/10 23:59:59 em Brasília
        $this->usuario(['created_at' => '2026-10-07 03:00:00']); // 07/10 00:00:00
        $this->usuario(['created_at' => '2026-10-07 13:00:00']);
        $this->usuario(['created_at' => '2026-10-08 03:00:00']); // 08/10 00:00:00

        $this->assertSame(2, $this->resumo()->novosUsuariosHoje());
    }

    #[Test]
    public function aViradaDoDiaAcontecePorMeiaNoiteDeSaoPauloENaoDeUtc(): void
    {
        $this->usuario(['created_at' => '2026-10-07 02:59:59']); // 06/10 em Brasília
        $this->usuario(['created_at' => '2026-10-07 03:00:00']); // 07/10
        $this->usuario(['created_at' => '2026-10-08 02:59:59']); // 07/10 às 23:59:59

        // 01:30Z de 08/10 ainda é 22:30 de 07/10 em Brasília, embora em UTC já seja outro dia.
        $this->irPara('2026-10-08T01:30:00+00:00');
        $this->assertSame(2, $this->resumo()->novosUsuariosHoje());

        // 03:00Z de 08/10 é a meia-noite de Brasília: hoje passa a ser 08/10.
        $this->irPara('2026-10-08T03:00:00+00:00');
        $this->assertSame(0, $this->resumo()->novosUsuariosHoje());
        $this->usuario(['created_at' => '2026-10-08 03:00:00']);
        $this->assertSame(1, $this->resumo()->novosUsuariosHoje());
    }

    // Online

    #[Test]
    public function onlineContaQuemTemVistoEmNosUltimosDoisMinutosInclusiveInvisivel(): void
    {
        $this->visto($this->usuario(), '2026-10-07 13:58:00.000'); // no limite: conta
        $this->visto($this->usuario(), '2026-10-07 13:57:59.999'); // passou do limite
        $this->visto($this->usuario(), null);                       // nunca visto
        $invisivel = $this->visto($this->usuario(), '2026-10-07 13:59:30.000');
        DB::table('users')->where('id', $invisivel->id)->update(['invisivel' => true]);

        $this->assertSame(2, $this->resumo()->onlineAgora());
    }

    // Mesas

    #[Test]
    public function mesaAbertaContaEEncerradaNaoConta(): void
    {
        $this->mesa();
        $this->mesa();
        $this->mesa(['situacao' => 'encerrada']);

        $this->assertSame(2, $this->resumo()->mesasAbertas());
    }

    #[Test]
    public function mesaFicaInativaDepoisDeQuinzeMinutosSemAtualizacao(): void
    {
        $this->mesa(['atualizada_em' => '2026-10-07 13:44:59.999']); // passou do limite
        $this->mesa(['atualizada_em' => '2026-10-07 13:45:00.000']); // exatamente 15 min: ainda não
        $this->mesa(['atualizada_em' => '2026-10-07 13:59:00.000']); // recente

        $this->assertSame(1, $this->resumo()->mesasInativas());
    }

    #[Test]
    public function mesaComPartidaEmAndamentoOuEncerradaNaoEInativa(): void
    {
        $this->mesa([...self::INATIVA, 'partida_em_andamento' => 1]);
        $this->mesa([...self::INATIVA, 'situacao' => 'encerrada']);

        $this->assertSame(0, $this->resumo()->mesasInativas());
    }

    #[Test]
    public function mesaComMembroAtivoOnlineNaoEInativa(): void
    {
        $online = $this->visto($this->usuario(), '2026-10-07 13:59:00.000');
        $offline = $this->visto($this->usuario(), '2026-10-07 13:00:00.000');
        $comOnline = $this->mesa(self::INATIVA);
        $this->membro($comOnline, (string) $offline->id, (string) $offline->id);
        $this->membro($comOnline, (string) $online->id, (string) $online->id);
        $soOffline = $this->mesa(self::INATIVA);
        $this->membro($soOffline, (string) $offline->id, (string) $offline->id);

        $this->assertSame(1, $this->resumo()->mesasInativas());
    }

    #[Test]
    public function membroQueSaiuOuContaExcluidaNaoImpedemAInatividade(): void
    {
        $online = $this->visto($this->usuario(), '2026-10-07 13:59:00.000');
        $mesa = $this->mesa(self::INATIVA);
        $this->membro($mesa, (string) $online->id, null, 'abandonada'); // saiu: usuario_ativo é nulo
        $this->membro($mesa, '999999', '999999');                       // conta que não existe mais

        $this->assertSame(1, $this->resumo()->mesasInativas());
    }

    #[Test]
    public function participanteLocalNaoInterfereNaInatividade(): void
    {
        $admin = $this->visto($this->usuario(), '2026-10-07 13:00:00.000');
        $online = $this->visto($this->usuario(), '2026-10-07 13:59:00.000'); // não é membro desta mesa
        $mesa = $this->mesa(self::INATIVA);
        $this->membro($mesa, (string) $admin->id, (string) $admin->id);
        $this->membro($mesa, "{$mesa}:local:1", null);
        // Id de local que começaria com o id de uma conta online: virar número casaria por prefixo.
        $this->membro($mesa, $online->id.'ab12cd34ef56ab78:local:2', null);

        $this->assertSame(1, $this->resumo()->mesasInativas());
    }

    // criada_em

    #[Test]
    public function novaMesaGravaCriadaEmEIssoNaoMudaDepois(): void
    {
        $admin = $this->usuario();
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($admin->identificadorNoDominio());
        $this->assertSame('2026-10-07 14:00:00.000', DB::table('mesas')->where('id', $mesa->id)->value('criada_em'));

        $this->relogio->avancar(3600);
        $plataforma->renomearMesa($mesa->id, 'Renomeada', $admin->identificadorNoDominio());
        $plataforma->encerrarMesa($mesa->id, $admin->identificadorNoDominio());

        $linha = DB::table('mesas')->where('id', $mesa->id)->first();
        $this->assertSame('2026-10-07 14:00:00.000', $linha->criada_em);
        $this->assertSame('2026-10-07 15:00:00.000', $linha->atualizada_em);
        $this->assertSame('2026-10-07 15:00:00.000', $linha->encerrada_em);
    }

    // Hoje

    #[Test]
    public function criadasHojeUsamCriadaEmNoDiaDeSaoPauloEIgnoramNulos(): void
    {
        $this->mesa(['criada_em' => '2026-10-07 02:59:59.999']); // ontem em Brasília
        $this->mesa(['criada_em' => '2026-10-07 03:00:00.000']);
        $this->mesa(['criada_em' => '2026-10-08 02:59:59.999']);
        $this->mesa(['criada_em' => '2026-10-08 03:00:00.000']); // amanhã
        $this->mesa(['criada_em' => null]);

        $this->assertSame(2, $this->resumo()->mesasCriadasHoje());
    }

    #[Test]
    public function finalizadasHojeUsamEncerradaEmNoDiaDeSaoPauloENaoAtualizadaEm(): void
    {
        $this->mesa(['situacao' => 'encerrada', 'encerrada_em' => '2026-10-07 03:00:00.000']);
        $this->mesa(['situacao' => 'encerrada', 'encerrada_em' => '2026-10-07 02:59:59.999']);
        $this->mesa(['situacao' => 'aberta', 'encerrada_em' => '2026-10-07 10:00:00.000']);
        $this->mesa(['situacao' => 'encerrada', 'encerrada_em' => null, 'atualizada_em' => '2026-10-07 13:00:00.000']);

        $this->assertSame(1, $this->resumo()->mesasFinalizadasHoje());
    }

    // Últimos 7 dias

    #[Test]
    public function ultimosSeteDiasSempreTemSeteLinhasDoMaisAntigoParaHoje(): void
    {
        $dias = $this->resumo()->ultimosSeteDias();

        $this->assertCount(7, $dias);
        $this->assertSame(['01/10', '02/10', '03/10', '04/10', '05/10', '06/10', '07/10'], array_column($dias, 'rotulo'));
        $this->assertSame('2026-10-01', $dias[0]['data']);
        $this->assertSame('2026-10-07', $dias[6]['data']);
        $this->assertSame(array_fill(0, 7, 0), array_column($dias, 'usuarios'));
        $this->assertSame(array_fill(0, 7, 0), array_column($dias, 'mesas'));
    }

    #[Test]
    public function ultimosSeteDiasAgrupaUsuariosPeloDiaDeSaoPaulo(): void
    {
        $this->usuario(['created_at' => '2026-10-01 02:59:59']); // 30/09 em Brasília: fora do período
        $this->usuario(['created_at' => '2026-10-01 03:00:00']); // 01/10
        $this->usuario(['created_at' => '2026-10-02 02:59:59']); // 01/10 às 23:59:59
        $this->usuario(['created_at' => '2026-10-02 03:00:00']); // 02/10
        $this->usuario(['created_at' => '2026-10-07 13:00:00']); // 07/10
        $this->usuario(['created_at' => '2026-10-08 03:00:00']); // amanhã: fora

        $this->assertSame([2, 1, 0, 0, 0, 0, 1], array_column($this->resumo()->ultimosSeteDias(), 'usuarios'));
    }

    #[Test]
    public function ultimosSeteDiasAgrupaMesasPeloDiaDeSaoPauloEIgnoraCriadaEmNula(): void
    {
        $this->mesa(['criada_em' => '2026-10-03 02:59:59.999']); // 02/10 em Brasília
        $this->mesa(['criada_em' => '2026-10-03 03:00:00.000']); // 03/10
        $this->mesa(['criada_em' => '2026-10-03 20:00:00.000']); // 03/10
        $this->mesa(['criada_em' => '2026-10-07 05:00:00.000']); // 07/10
        $this->mesa(['criada_em' => '2026-09-20 12:00:00.000']); // antes do período
        $this->mesa(['criada_em' => null]);

        $this->assertSame([0, 1, 2, 0, 0, 0, 1], array_column($this->resumo()->ultimosSeteDias(), 'mesas'));
    }
}
