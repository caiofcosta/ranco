<?php

declare(strict_types=1);

namespace Tests\Feature\Premiacao;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ComponentesDePremiacaoTest extends TestCase
{
    #[Test]
    public function medalhaUsaAImagemEADescricaoDeCadaColocacaoEEmpate(): void
    {
        foreach ([1 => 'ouro', 2 => 'prata', 3 => 'bronze'] as $colocacao => $cor) {
            foreach ([false, true] as $empate) {
                $arquivo = 'medalha-'.($empate ? 'dupla-' : '').$cor.'.png';
                $descricao = $empate
                    ? "Medalha dupla de {$cor}, empate em {$colocacao}º lugar"
                    : "Medalha de {$cor}, {$colocacao}º lugar";

                $this->blade(
                    '<x-medalha :colocacao="$colocacao" :empate="$empate"
                        jogo="Labirinto" mesa="Mesa da Ana" data="03/10/2026" />',
                    compact('colocacao', 'empate'),
                )
                    ->assertSee('src="'.asset('images/medalhas/'.$arquivo).'"', false)
                    ->assertSee('alt="'.$descricao.'"', false);
            }
        }
    }

    #[Test]
    public function medalhaSemFraseNemLogoPreservaJogoMesaEData(): void
    {
        $conteudo = '<x-medalha :colocacao="1" jogo="Labirinto"
            mesa="Mesa da Ana" data="03/10/2026" />';

        $pagina = $this->blade($conteudo);

        $pagina
            ->assertSeeInOrder(['Labirinto', 'Mesa da Ana', '03/10/2026'])
            ->assertDontSee('text-lg font-bold', false);

        $this->assertSame(1, substr_count((string) $pagina, '<img'));
    }

    #[Test]
    public function medalhaAceitaFraseELogoNulosOuVazios(): void
    {
        foreach ([null, ''] as $ausente) {
            $pagina = $this->blade(
                '<x-medalha :colocacao="1" jogo="Labirinto"
                    :logo="$ausente" :frase="$ausente"
                    mesa="Mesa da Ana" data="03/10/2026" />',
                compact('ausente'),
            );

            $pagina
                ->assertSee('Labirinto')
                ->assertSee('Mesa da Ana')
                ->assertDontSee('text-lg font-bold', false);

            $this->assertSame(1, substr_count((string) $pagina, '<img'));
        }
    }

    #[Test]
    public function medalhaMostraLogoJogoFraseMesaEDataNaOrdem(): void
    {
        $this->blade(
            '<x-medalha :colocacao="2"
                jogo="Labirinto" logo="images/jogos/labirinto.svg"
                frase="Frase recebida por parametro."
                mesa="Mesa da Ana" data="03/10/2026" />',
        )->assertSeeInOrder([
            asset('images/jogos/labirinto.svg'),
            'Labirinto',
            'Frase recebida por parametro.',
            'Mesa da Ana',
            '03/10/2026',
        ]);
    }

    #[Test]
    public function medalhaEscapaTodosOsTextosRecebidos(): void
    {
        $texto = '<script>alert("teste")</script>';

        $pagina = $this->blade(
            '<x-medalha :colocacao="1" :jogo="$texto" :frase="$texto"
                :mesa="$texto" :data="$texto" />',
            compact('texto'),
        );

        $pagina->assertDontSee($texto, false);
        $this->assertSame(4, substr_count((string) $pagina, e($texto)));
    }

    #[Test]
    public function trofeuDeVitoriaMostraNomeCompletoComQuarentaCaracteres(): void
    {
        $mesa = str_repeat('M', 40);

        $this->blade(
            '<x-trofeu tipo="vitoria" :mesa="$mesa" data="03/10/2026" />',
            compact('mesa'),
        )
            ->assertSee('src="'.asset('images/trofeus/trofeu-vitoria.png').'"', false)
            ->assertSee('alt="Troféu de vitória, mesa '.$mesa.'"', false)
            ->assertSeeInOrder(['data-test="placa-do-trofeu"', 'VITÓRIA', $mesa, '03/10/2026', '<figcaption', $mesa], false)
            ->assertDontSee('FORÇA EM DOBRO');
    }

    #[Test]
    public function trofeuDeEmpateMostraForcaEmDobroSemMesaNaPlaca(): void
    {
        $mesa = 'Mesa reservada';

        $this->blade(
            '<x-trofeu tipo="empate" :mesa="$mesa" data="03/10/2026" />',
            compact('mesa'),
        )
            ->assertSee('src="'.asset('images/trofeus/trofeu-empate.png').'"', false)
            ->assertSee('alt="Troféu de empate, mesa '.$mesa.'"', false)
            ->assertSeeInOrder(['data-test="placa-do-trofeu"', 'EMPATE', 'FORÇA EM DOBRO', '03/10/2026', '<figcaption', $mesa], false)
            ->assertDontSee('VITÓRIA');
    }

    #[Test]
    public function trofeuEscapaMesaEDataInclusiveNoTextoAlternativo(): void
    {
        $texto = '<script>alert("teste")</script>';

        foreach (['vitoria', 'empate'] as $tipo) {
            $pagina = $this->blade(
                '<x-trofeu :tipo="$tipo" :mesa="$texto" :data="$texto" />',
                compact('tipo', 'texto'),
            );

            $pagina->assertDontSee($texto, false);

            $this->assertSame(
                $tipo === 'vitoria' ? 4 : 3,
                substr_count((string) $pagina, e($texto)),
            );
        }
    }
}
