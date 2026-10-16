<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocador;
use App\Filament\Resources\Frases\Pages\GerenciarFrases;
use App\Filament\Resources\Momentos\Pages\GerenciarMomentos;
use App\Infra\Banco\PdoRepositorioDeProvocacoes;
use App\Models\FraseDeProvocacao;
use App\Models\MomentoDeProvocacao;
use App\Models\TemaDeProvocacao;
use App\Models\User;
use App\Provocacao\TipoDeTema;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProvocacoesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function soAdministradorDoSistemaAbreACuradoriaNoPainel(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $admin = User::factory()->create(['email' => 'admin@exemplo.com']);
        $qualquer = User::factory()->create(['email' => 'qualquer@exemplo.com']);

        $this->actingAs($admin)->get('/painel/frases')->assertOk();
        $this->actingAs($admin)->get('/painel/temas')->assertOk();
        $this->actingAs($admin)->get('/painel/momentos')->assertOk();

        $this->actingAs($qualquer)->get('/painel/frases')->assertForbidden();
        $this->actingAs($qualquer)->get('/painel/temas')->assertForbidden();
        $this->actingAs($qualquer)->get('/painel/momentos')->assertForbidden();
    }

    #[Test]
    public function oAdministradorCriaUmaFraseComTemas(): void
    {
        $this->entrarComoAdministrador();

        Livewire::test(GerenciarFrases::class)
            ->callAction('create', data: [
                'texto' => 'Frase nova de curadoria para o painel.',
                'intensidade' => 2,
                'perspectiva' => Perspectiva::Pessoa->value,
                'temas' => ['azar'],
                'ativa' => true,
            ])
            ->assertHasNoActionErrors();

        $frase = FraseDeProvocacao::query()->where('texto', 'Frase nova de curadoria para o painel.')->sole();
        $this->assertSame(2, $frase->intensidade);
        $this->assertSame(Perspectiva::Pessoa, $frase->perspectiva);
        $this->assertSame(['azar'], $frase->temas->pluck('codigo')->all());
    }

    #[Test]
    public function aceitaVariavelOferecidaPeloMomentoDoTema(): void
    {
        $this->entrarComoAdministrador();

        Livewire::test(GerenciarFrases::class)
            ->callAction('create', data: [
                'texto' => 'Já deu {tentativa} erros nesta sessão de teste.',
                'intensidade' => 1,
                'perspectiva' => Perspectiva::Pessoa->value,
                'temas' => ['acesso'],
                'ativa' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(FraseDeProvocacao::query()->where('texto', 'Já deu {tentativa} erros nesta sessão de teste.')->exists());
    }

    #[Test]
    public function recusaVariavelQueOMomentoNaoOferece(): void
    {
        $this->entrarComoAdministrador();

        Livewire::test(GerenciarFrases::class)
            ->callAction('create', data: [
                'texto' => '{vencedor} não cabe neste tema de acesso.',
                'intensidade' => 1,
                'perspectiva' => Perspectiva::Pessoa->value,
                'temas' => ['acesso'],
                'ativa' => true,
            ])
            ->assertHasActionErrors(['texto']);

        $this->assertFalse(FraseDeProvocacao::query()->where('texto', '{vencedor} não cabe neste tema de acesso.')->exists());
    }

    #[Test]
    public function desativarTiraAFraseDoSorteio(): void
    {
        $this->entrarComoAdministrador();
        $this->isolarMomentoDosLinks();

        Livewire::test(GerenciarFrases::class)
            ->callAction('create', data: [
                'texto' => 'Frase unica para o teste de desativar.',
                'intensidade' => 1,
                'perspectiva' => Perspectiva::Pessoa->value,
                'temas' => ['links'],
                'ativa' => true,
            ])
            ->assertHasNoActionErrors();

        $frase = FraseDeProvocacao::query()->where('texto', 'Frase unica para o teste de desativar.')->sole();
        $this->assertSame('Frase unica para o teste de desativar.', $this->sortearLimiteDeEmails());

        Livewire::test(GerenciarFrases::class)
            ->callTableAction('edit', $frase, data: [
                'texto' => $frase->texto,
                'intensidade' => $frase->intensidade,
                'perspectiva' => $frase->perspectiva->value,
                'temas' => ['links'],
                'ativa' => false,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNull($this->sortearLimiteDeEmails());
    }

    #[Test]
    public function editarOsTemasDeUmMomentoMudaAsFrasesQueEleSorteia(): void
    {
        $this->entrarComoAdministrador();
        $this->isolarMomentoDosLinks();

        TemaDeProvocacao::query()->create([
            'codigo' => 'teste_curadoria',
            'tipo' => TipoDeTema::Contexto,
            'significado' => 'Só para o teste da curadoria',
        ]);
        $frase = FraseDeProvocacao::query()->create([
            'texto' => 'Frase isolada do teste de tema do momento.',
            'intensidade' => 1,
            'perspectiva' => Perspectiva::Pessoa,
            'ativa' => true,
        ]);
        $frase->temas()->sync(['teste_curadoria']);

        $this->assertNull($this->sortearLimiteDeEmails());

        $momento = MomentoDeProvocacao::query()->findOrFail('conta.limite_de_emails');
        Livewire::test(GerenciarMomentos::class)
            ->callTableAction('edit', $momento, data: [
                'descricao' => $momento->descricao,
                'temas' => ['teste_curadoria'],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Frase isolada do teste de tema do momento.', $this->sortearLimiteDeEmails());

        Livewire::test(GerenciarMomentos::class)
            ->callTableAction('edit', $momento, data: [
                'descricao' => $momento->descricao,
                'temas' => ['links'],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNull($this->sortearLimiteDeEmails());
    }

    #[Test]
    public function oAdministradorDuplicaUmaFrase(): void
    {
        $this->entrarComoAdministrador();
        $original = FraseDeProvocacao::query()->where('texto', 'Hoje o login resolveu competir também.')->sole();

        Livewire::test(GerenciarFrases::class)
            ->callTableAction('replicate', $original, data: [
                'texto' => 'Cópia da frase para o teste de duplicar.',
                'temas' => $original->temas->pluck('codigo')->all(),
            ])
            ->assertHasNoTableActionErrors();

        $copia = FraseDeProvocacao::query()->where('texto', 'Cópia da frase para o teste de duplicar.')->sole();
        $this->assertSame($original->intensidade, $copia->intensidade);
        $this->assertSame($original->perspectiva, $copia->perspectiva);
        $this->assertEqualsCanonicalizing(
            $original->temas->pluck('codigo')->all(),
            $copia->temas->pluck('codigo')->all(),
        );
    }

    private function entrarComoAdministrador(): void
    {
        config(['ranco.administradores' => ['admin@exemplo.com']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@exemplo.com']));
        Filament::setCurrentPanel('painel');
    }

    private function isolarMomentoDosLinks(): void
    {
        FraseDeProvocacao::query()
            ->whereHas('temas', static fn ($consulta) => $consulta->where('temas_de_provocacao.codigo', 'links'))
            ->update(['ativa' => false]);
    }

    private function sortearLimiteDeEmails(): ?string
    {
        $provocador = new Provocador(new PdoRepositorioDeProvocacoes(DB::connection()->getPdo()));

        return $provocador->sortear(Momento::LimiteDeEmails, 1, ['minutos' => '3'], Perspectiva::Pessoa);
    }
}
