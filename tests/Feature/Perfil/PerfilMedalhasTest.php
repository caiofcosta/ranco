<?php

declare(strict_types=1);

namespace Tests\Feature\Perfil;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocador;
use App\Mesa\NomeDaMesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PerfilMedalhasTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function exigeLoginEEmailVerificado(): void
    {
        $this->get(route('perfil.mostrar'))->assertRedirect(route('login'));
        $usuario = User::factory()->unverified()->create();
        $this->actingAs($usuario)->get(route('perfil.mostrar'))
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function semMedalhasMostraEstadoVazioEPermiteEditarAConta(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana']);
        $this->actingAs($usuario)->get(route('perfil.mostrar'))
            ->assertOk()->assertViewIs('perfil.mostrar')
            ->assertSee('Ana')->assertSee('Nenhuma medalha ainda.')
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertSee('href="'.route('perfil.mostrar').'"', false)
            ->assertDontSee('data-test="medalha"', false);
    }

    #[Test]
    public function mostraSomenteMedalhasDaContaMesmoComParametrosDeOutraPessoa(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        $mesa = $this->mesaDe($ana);
        $this->gravarMedalha($mesa, 1, $ana->identificadorNoDominio(), 'Medalha da Ana.');
        $this->gravarMedalha($mesa, 2, $bruno->identificadorNoDominio(), 'Medalha do Bruno.');

        $this->actingAs($ana)->get(route('perfil.mostrar', [
            'pessoa' => $bruno->id,
            'usuario' => $bruno->id,
        ]))->assertOk()->assertSee('Medalha da Ana.')->assertDontSee('Medalha do Bruno.');
        $this->get('/perfil/'.$bruno->id)->assertForbidden();
        $this->actingAs($bruno)->get(route('perfil.mostrar'))
            ->assertOk()->assertSee('Medalha do Bruno.')->assertDontSee('Medalha da Ana.');
    }

    #[Test]
    public function mostraFraseMesaEDataDaMaisRecenteParaAMaisAntigaSemRegravar(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $mesa = $this->mesaDe($ana, 'Mesa de sexta');
        $this->gravarMedalha($mesa, 1, $ana->identificadorNoDominio(), 'Frase antiga.', '2026-10-01 12:00:00.000');
        $this->gravarMedalha($mesa, 2, $ana->identificadorNoDominio(), 'Frase recente.', '2026-10-03 12:00:00.000');
        $this->gravarMedalha($mesa, 3, $ana->identificadorNoDominio(), 'Outra no mesmo instante.', '2026-10-03 12:00:00.000');
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());
        $antes = DB::table('medalhas')->where('mesa_id', $mesa->id)->orderBy('id')->get()->toJson();

        $this->actingAs($ana)->get(route('perfil.mostrar'))
            ->assertOk()->assertSee('Mesa de sexta')
            ->assertSee('01/10/2026')->assertSee('03/10/2026')
            ->assertSeeInOrder(['Outra no mesmo instante.', 'Frase recente.', 'Frase antiga.'])
            ->assertViewHas('medalhas', fn (array $medalhas) => count($medalhas) === 3);
        $this->get(route('perfil.mostrar'))->assertOk();
        $this->assertSame($antes, DB::table('medalhas')->where('mesa_id', $mesa->id)->orderBy('id')->get()->toJson());
    }

    #[Test]
    public function medalhasLocaisSoAparecemAposReconhecimentoPelaPropriaConta(): void
    {
        $admin = User::factory()->create();
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        $mesa = $this->mesaDe($admin);
        $plataforma = app(Plataforma::class);
        $pendente = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Ana local', $ana->email, $admin->identificadorNoDominio());
        $rejeitada = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Ana rejeitada', $ana->email, $admin->identificadorNoDominio());
        $deBruno = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Bruno local', $bruno->email, $admin->identificadorNoDominio());
        $this->gravarMedalha($mesa, 1, $pendente, 'Frase da participação reconhecível.');
        $this->gravarMedalha($mesa, 2, $rejeitada, 'Frase da participação rejeitada.');
        $this->gravarMedalha($mesa, 3, $deBruno, 'Frase da participação do Bruno.');
        $plataforma->rejeitarParticipacaoLocal($rejeitada, $ana->email);
        $plataforma->reconhecerParticipacaoLocal($deBruno, $bruno->identificadorNoDominio(), $bruno->email);

        foreach ([$ana, $admin] as $usuario) {
            $this->actingAs($usuario)->get(route('perfil.mostrar'))
                ->assertOk()->assertSee('Nenhuma medalha ainda.')
                ->assertDontSee('Frase da participação');
        }

        $plataforma->reconhecerParticipacaoLocal($pendente, $ana->identificadorNoDominio(), $ana->email);
        $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertSee('Frase da participação reconhecível.')
            ->assertDontSee('Frase da participação rejeitada.')
            ->assertDontSee('Frase da participação do Bruno.')
            ->assertViewHas('medalhas', fn (array $medalhas) => count($medalhas) === 1);
    }

    #[Test]
    public function medalhaSemFraseContinuaVisivelComNomePadraoDaMesaEData(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $mesa = $this->mesaDe($ana, '');
        $this->gravarMedalha($mesa, 1, $ana->identificadorNoDominio(), null);
        $this->actingAs($ana)->get(route('perfil.mostrar'))
            ->assertOk()->assertSee('Mesa de Ana')->assertSee('03/10/2026')
            ->assertSee('data-test="medalha"', false)
            ->assertDontSee('Nenhuma medalha ainda.')
            ->assertDontSee('comentário completamente necessário');
    }

    #[Test]
    public function escapaTextosGravadosEFormataADataEmBrasiliaMesmoComAplicacaoEmUtc(): void
    {
        config(['app.timezone' => 'UTC']);
        $ana = User::factory()->create();
        $mesa = $this->mesaDe($ana, '<b>Mesa</b>');
        $this->gravarMedalha($mesa, 1, $ana->identificadorNoDominio(), '<script>frase</script>', '2026-10-03 01:00:00.000');
        $this->actingAs($ana)->get(route('perfil.mostrar'))
            ->assertOk()->assertSee('<b>Mesa</b>')->assertSee('<script>frase</script>')
            ->assertDontSee('<script>frase</script>', false)
            ->assertSee('02/10/2026')->assertSee('2026-10-02T22:00:00-03:00', false);
    }

    #[Test]
    public function amigoAceitoVeMedalhasSemDadosDaContaENomeNaListaAbreOPerfil(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = $this->mesaDe($bruno, 'Mesa registrada');
        $this->gravarMedalha($mesa, 1, $bruno->identificadorNoDominio(), 'Frase do Bruno.');
        app(Plataforma::class)->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());

        $this->actingAs($ana)->get(route('perfil.amigo', $bruno->id))
            ->assertOk()->assertViewIs('perfil.mostrar')
            ->assertSee('Bruno')->assertSee('Frase do Bruno.')
            ->assertSee('Mesa registrada')->assertSee('03/10/2026')
            ->assertDontSee($bruno->email)->assertDontSee('Editar conta');
        $this->get(route('amigos.index'))->assertOk()
            ->assertSee('href="'.route('perfil.amigo', $bruno->id).'"', false);

        app(Plataforma::class)->desfazerAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $this->get(route('perfil.amigo', $bruno->id))->assertForbidden()->assertDontSee('Frase do Bruno.');
    }

    #[Test]
    public function naoAmigoEContaInexistenteRecebemExatamenteAMesmaPaginaPrivada(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create(['name' => 'Nome que deve ficar privado']);
        $mesa = $this->mesaDe($bruno);
        $this->gravarMedalha($mesa, 1, $bruno->identificadorNoDominio(), 'Frase que deve ficar privada.');
        $inexistente = (string) (User::query()->max('id') + 1000);
        $resposta = $this->actingAs($ana)->get(route('perfil.amigo', $bruno->id))
            ->assertForbidden()->assertViewIs('perfil.mostrar')
            ->assertSee('Para ver as medalhas, é preciso ser amigo. Envie um convite pela tela Amigos.')
            ->assertSee('href="'.route('amigos.index').'"', false)
            ->assertDontSee($bruno->name)->assertDontSee($bruno->email)
            ->assertDontSee('Frase que deve ficar privada.')->assertDontSee('Editar conta');
        $this->assertContains($resposta->viewData('provocacao'), [
            'Vocês não são amigos. Ainda dá tempo de mudar isso.',
            'Esse perfil é só para amigos, e aqui ainda não existe amizade.',
        ]);
        $ausente = $this->get(route('perfil.amigo', $inexistente))->assertForbidden();
        // O Vite nao repete os preloads de fontes na mesma instancia da aplicacao de teste.
        // Ignoramos essas linhas para comparar integralmente o restante das duas paginas.
        // Livewire gera ids próprios a cada requisição; o resto do HTML precisa ser idêntico.
        $semPreCarregamento = static fn (string $html): string => preg_replace(
            ['/wire:(snapshot|id|key)="[^"]*"/', "/fluxModal\\('([^']*)', '[^']*'\\)/"],
            ['wire:$1=""', "fluxModal('$1', '')"],
            implode("\n", array_filter(
                explode("\n", $html),
                static fn (string $linha): bool => ! str_contains($linha, 'rel="preload"'),
            )),
        );
        $this->assertSame($semPreCarregamento($resposta->getContent()), $semPreCarregamento($ausente->getContent()));
        $this->assertSame($resposta->headers->get('Cache-Control'), $ausente->headers->get('Cache-Control'));
        $this->get(route('perfil.amigo', 'invalida'))->assertForbidden();
    }

    #[Test]
    public function rotaDeAmigoExigeLoginEEmailVerificadoEPermiteVerOProprioIdentificador(): void
    {
        $ana = User::factory()->create();
        $this->get(route('perfil.amigo', $ana->id))->assertRedirect(route('login'));
        $semVerificacao = User::factory()->unverified()->create();
        $this->actingAs($semVerificacao)->get(route('perfil.amigo', $ana->id))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($ana)->get(route('perfil.amigo', $ana->id))
            ->assertOk()->assertDontSee('Editar conta');
    }

    #[Test]
    public function perfilPrivadoContinuaInformativoSemFraseDisponivel(): void
    {
        $ana = User::factory()->create();
        $this->actingAs($ana)->withSession(['perfil.provocacao_privada' => ''])
            ->get(route('perfil.amigo', 'inexistente'))->assertForbidden()
            ->assertSee('Para ver as medalhas, é preciso ser amigo. Envie um convite pela tela Amigos.')
            ->assertDontSee('comentário completamente necessário');
    }

    #[Test]
    public function concessaoPreservaONomeDaMesaDepoisDeRenomear(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create();
        $mesa = $this->mesaDe($ana, 'Nome na conquista');
        $plataforma = app(Plataforma::class);
        $plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $bruno): void {
            $mesa->adicionarMembro($bruno->identificadorNoDominio());
            $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, 1), $ana->identificadorNoDominio());
            $partida->iniciar($ana->identificadorNoDominio());
            foreach ([[$ana, 9000], [$bruno, 12000]] as [$pessoa, $tempo]) {
                $partida->iniciarTentativa($pessoa->identificadorNoDominio());
                $partida->concluirTentativa($pessoa->identificadorNoDominio(), $tempo);
            }
        });
        $antes = DB::table('medalhas')->where('mesa_id', $mesa->id)->first();
        $this->assertNotNull($antes);
        $this->assertSame('Nome na conquista', $antes->mesa_nome);
        $plataforma->renomearMesa($mesa->id, 'Nome depois da conquista', $ana->identificadorNoDominio());
        $depois = DB::table('medalhas')->where('mesa_id', $mesa->id)->first();
        $this->assertEquals($antes, $depois);
        $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()
            ->assertSee('Nome na conquista')->assertDontSee('Nome depois da conquista');
    }

    #[Test]
    public function momentoPrivadoTemTemaProprioEFrasesCadastradas(): void
    {
        $this->assertDatabaseHas('momentos_de_provocacao', ['codigo' => Momento::PerfilPrivado->value]);
        $this->assertDatabaseHas('temas_de_provocacao', ['codigo' => 'perfil_privado', 'tipo' => 'contexto']);
        $this->assertDatabaseHas('momentos_temas', ['momento' => 'perfil.privado', 'tema' => 'perfil_privado']);
        foreach ([
            'Vocês não são amigos. Ainda dá tempo de mudar isso.',
            'Esse perfil é só para amigos, e aqui ainda não existe amizade.',
        ] as $frase) {
            $this->assertDatabaseHas('provocacoes', ['texto' => $frase, 'perspectiva' => 'pessoa']);
        }
        $this->assertNotNull(app(Provocador::class)->sortear(Momento::PerfilPrivado));
    }

    private function mesaDe(User $admin, string $nome = 'Grupo'): Mesa
    {
        return app(Plataforma::class)->criarMesa($admin->identificadorNoDominio(), $nome);
    }

    private function gravarMedalha(Mesa $mesa, int $partida, string $pessoa, ?string $frase, string $data = '2026-10-03 12:00:00.000'): void
    {
        DB::table('medalhas')->insert([
            'pessoa' => $pessoa,
            'mesa_id' => $mesa->id,
            'mesa_nome' => NomeDaMesa::de($mesa),
            'partida' => $partida,
            'colocacao' => 1,
            'empate' => false,
            'jogo' => 'labirinto',
            'frase' => $frase,
            'concedida_em' => $data,
        ]);
    }
}
