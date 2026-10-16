<?php

declare(strict_types=1);

namespace Tests\Feature\Conta;

use App\Dominio\Mesa\SituacaoReconhecimento;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ParticipacoesLocaisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function aContaVeAParticipacaoPendenteDoProprioEmail(): void
    {
        ['bruno' => $bruno, 'id' => $id] = $this->pendenciaDeBruno();

        $this->actingAs($bruno)
            ->get(route('conta.participacoes-locais'))
            ->assertOk()
            ->assertSee('Participações locais', false)
            ->assertSee('Bruno', false)
            ->assertSee('Grupo do ranço', false)
            ->assertSee('Reconhecer inclui a participação no histórico da conta.', false)
            ->assertSee(route('conta.participacoes-locais.reconhecer', $id), false)
            ->assertSee(route('conta.participacoes-locais.rejeitar', $id), false);
    }

    #[Test]
    public function semPendenciaATelaFicaVazia(): void
    {
        $bruno = User::factory()->create(['email' => 'bruno@exemplo.com']);

        $this->actingAs($bruno)
            ->get(route('conta.participacoes-locais'))
            ->assertOk()
            ->assertSee('Nenhuma participação pendente.', false)
            ->assertDontSee('Reconhecer inclui a participação no histórico da conta.', false);
    }

    #[Test]
    public function reconhecerEntraNoHistoricoOficialESomeDaLista(): void
    {
        ['bruno' => $bruno, 'id' => $id] = $this->pendenciaDeBruno();

        $this->actingAs($bruno)
            ->post(route('conta.participacoes-locais.reconhecer', $id))
            ->assertRedirect(route('conta.participacoes-locais'))
            ->assertSessionHas('status', 'Participação reconhecida.');

        $this->assertSame([$id], app(Plataforma::class)->participacoesReconhecidasDe($bruno->identificadorNoDominio()));

        $this->actingAs($bruno)
            ->get(route('conta.participacoes-locais'))
            ->assertOk()
            ->assertSee('Nenhuma participação pendente.', false)
            ->assertDontSee('data-test="reconhecer-participacao-local"', false);
    }

    #[Test]
    public function rejeitarNaoEntraNoHistoricoOficial(): void
    {
        ['bruno' => $bruno, 'id' => $id, 'mesa' => $mesa] = $this->pendenciaDeBruno();

        $this->actingAs($bruno)
            ->post(route('conta.participacoes-locais.rejeitar', $id))
            ->assertRedirect(route('conta.participacoes-locais'))
            ->assertSessionHas('status', 'Participação rejeitada.');

        $local = app(Plataforma::class)->mesa($mesa)->participanteLocal($id);
        $this->assertNotNull($local);
        $this->assertSame(SituacaoReconhecimento::Rejeitada, $local->situacao);
        $this->assertSame([], app(Plataforma::class)->participacoesReconhecidasDe($bruno->identificadorNoDominio()));
    }

    #[Test]
    public function outraContaNaoVeNemReconheceAParticipacao(): void
    {
        ['id' => $id] = $this->pendenciaDeBruno();
        $carla = User::factory()->create(['name' => 'Carla', 'email' => 'carla@exemplo.com']);

        $this->actingAs($carla)
            ->get(route('conta.participacoes-locais'))
            ->assertOk()
            ->assertDontSee('Grupo do ranço', false);

        $this->actingAs($carla)
            ->post(route('conta.participacoes-locais.reconhecer', $id))
            ->assertSessionHasErrors('participacao');

        $this->assertSame([], app(Plataforma::class)->participacoesReconhecidasDe($carla->identificadorNoDominio()));
        $this->assertCount(1, app(Plataforma::class)->participacoesLocaisPendentes('bruno@exemplo.com'));
    }

    #[Test]
    public function visitanteNaoAbreATela(): void
    {
        $this->get(route('conta.participacoes-locais'))->assertRedirect(route('login'));
    }

    /** @return array{bruno: User, id: string, mesa: string} */
    private function pendenciaDeBruno(): array
    {
        $ana = User::factory()->create(['name' => 'Ana', 'email' => 'ana@exemplo.com']);
        $bruno = User::factory()->create(['name' => 'Bruno', 'email' => 'bruno@exemplo.com']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo do ranço');
        $id = $plataforma->cadastrarParticipanteLocal(
            $mesa->id,
            'Bruno',
            'Bruno@Exemplo.com',
            $ana->identificadorNoDominio(),
        );

        return ['bruno' => $bruno, 'id' => $id, 'mesa' => $mesa->id];
    }
}
