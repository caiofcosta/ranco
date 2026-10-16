<?php

declare(strict_types=1);

namespace Tests\Feature\Perfil;

use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MenuDoPerfilTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function pessoaVerificadaVeOLinkDepoisDeAmigosNoInicio(): void
    {
        $pessoa = User::factory()->create();
        $resposta = $this->actingAs($pessoa)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Amigos', 'Medalhas e troféus']);
        $item = $this->itemDoMenu($resposta->getContent());

        $this->assertSame(route('perfil.mostrar'), $item->getAttribute('href'));
        $this->assertSame('Medalhas e troféus', $item->getAttribute('aria-label'));
        $this->assertStringContainsString('Medalhas e troféus', $item->textContent);
        $this->assertTrue($item->hasAttribute('wire:navigate'));
        $this->assertFalse($item->hasAttribute('aria-current'));
        // O menu lateral passou a usar ícones (Flux) no lugar da inicial: o primeiro quadradinho traz o ícone.
        $quadradinho = $item->getElementsByTagName('span')->item(0);
        $this->assertInstanceOf(DOMElement::class, $quadradinho);
        $this->assertGreaterThanOrEqual(1, $quadradinho->getElementsByTagName('svg')->length);
    }

    #[Test]
    public function cartaoDoUsuarioAbreOPerfilDaConta(): void
    {
        $pessoa = User::factory()->create(['name' => 'Ana Costa']);
        $inicio = $this->actingAs($pessoa)->get(route('dashboard'))->assertOk()->getContent();
        $cartao = $this->cartaoDaConta($inicio);

        $this->assertSame(route('profile.edit'), $cartao->getAttribute('href'));
        $this->assertSame('Perfil e conta', $cartao->getAttribute('aria-label'));
        $this->assertStringContainsString('Ana Costa', $cartao->textContent);
        $this->assertStringContainsString('Perfil e conta', $cartao->textContent);
        $this->assertFalse($cartao->hasAttribute('aria-current'));

        $conta = $this->get(route('profile.edit'))->assertOk()->getContent();
        $this->assertSame('page', $this->cartaoDaConta($conta)->getAttribute('aria-current'));
    }

    #[Test]
    public function destaqueApareceSomenteNoPerfilProprio(): void
    {
        $pessoa = User::factory()->create();
        $amigo = User::factory()->create();
        app(Plataforma::class)->aceitarConviteDeAmizade($pessoa->identificadorNoDominio(), $amigo->identificadorNoDominio());
        $this->actingAs($pessoa);

        foreach ([route('perfil.mostrar') => true, route('amigos.index') => false, route('perfil.amigo', $amigo->id) => false] as $endereco => $ativo) {
            $item = $this->itemDoMenu($this->get($endereco)->assertOk()->getContent());
            $classes = explode(' ', $item->getAttribute('class'));
            $quadradinho = $item->getElementsByTagName('span')->item(0);

            $this->assertSame($ativo, $item->hasAttribute('aria-current'));
            $this->assertSame($ativo ? 'page' : '', $item->getAttribute('aria-current'));
            $this->assertSame($ativo, in_array('bg-cream', $classes, true));
            $this->assertSame($ativo, in_array('text-coal', $classes, true));
            $this->assertSame($ativo, in_array('bg-coral', explode(' ', $quadradinho->getAttribute('class')), true));
        }
    }

    #[Test]
    public function visitanteERedirecionadoSemVerOMenu(): void
    {
        foreach (['dashboard', 'perfil.mostrar', 'amigos.index'] as $rota) {
            $this->get(route($rota))->assertRedirect(route('login'))->assertDontSee('Medalhas e troféus');
        }
    }

    private function itemDoMenu(string $html): DOMElement
    {
        $documento = new DOMDocument;
        $errosAnteriores = libxml_use_internal_errors(true);
        try {
            $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errosAnteriores);
        }
        $consulta = new DOMXPath($documento);
        $itens = $consulta->query('//nav[@aria-label="Painel"]/a[@href="'.route('perfil.mostrar').'"]');
        $this->assertSame(1, $itens->length);

        return $itens->item(0);
    }

    private function cartaoDaConta(string $html): DOMElement
    {
        $documento = new DOMDocument;
        $errosAnteriores = libxml_use_internal_errors(true);
        try {
            $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errosAnteriores);
        }
        $consulta = new DOMXPath($documento);
        $itens = $consulta->query('//a[@aria-label="Perfil e conta"]');
        $this->assertSame(1, $itens->length);

        return $itens->item(0);
    }
}
