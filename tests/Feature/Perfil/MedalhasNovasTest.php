<?php

declare(strict_types=1);

namespace Tests\Feature\Perfil;

use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MedalhasNovasTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function perfilUsaImagemDeCadaColocacaoEEmpateComNomeELogoDoJogo(): void
    {
        $usuario = User::factory()->create();
        $mesa = app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio(), 'Medalhas novas');
        $partida = 0;
        foreach ([1 => 'ouro', 2 => 'prata', 3 => 'bronze'] as $colocacao => $cor) {
            foreach ([false, true] as $empate) {
                $this->medalha($mesa->id, $usuario->identificadorNoDominio(), ++$partida, [
                    'colocacao' => $colocacao,
                    'empate' => $empate,
                    'frase' => $empate ? null : "Frase da medalha de {$cor}.",
                ]);
            }
        }

        $resposta = $this->actingAs($usuario)->get('/perfil')->assertOk();
        $dom = $this->dom($resposta->getContent());
        foreach ([1 => 'ouro', 2 => 'prata', 3 => 'bronze'] as $colocacao => $cor) {
            foreach ([false, true] as $empate) {
                $arquivo = 'medalha-'.($empate ? 'dupla-' : '').$cor.'.png';
                $this->assertSame(1, $dom->query('//li[@data-test="medalha"]//img[contains(@src, "'.$arquivo.'")]')->length);
            }
        }
        $this->assertSame(6, $dom->query('//li[@data-test="medalha"]//img[contains(@src, "images/jogos/labirinto.svg")]')->length);
        $this->assertSame(6, $dom->query('//li[@data-test="medalha"]//span[text()="Labirinto"]')->length);
        $this->assertSame(3, $dom->query('//li[@data-test="medalha"]//p[contains(text(), "Frase da medalha")]')->length);
        $resposta->assertSee('Medalhas novas')->assertSee('02/10/2026')
            ->assertSee('2026-10-02T22:00:00-03:00', false);
    }

    #[Test]
    public function jogoDesconhecidoUsaCodigoEscapadoSemLogoEFraseNula(): void
    {
        $usuario = User::factory()->create();
        $mesa = app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio(), 'Outro jogo');
        $this->medalha($mesa->id, $usuario->identificadorNoDominio(), 1, [
            'jogo' => '<b>desconhecido</b>',
            'frase' => null,
        ]);

        $resposta = $this->actingAs($usuario)->get('/perfil')->assertOk()
            ->assertSee('<b>desconhecido</b>')->assertDontSee('<b>desconhecido</b>', false)
            ->assertSee('02/10/2026')->assertSee('Medalhas novas');
        $dom = $this->dom($resposta->getContent());
        $this->assertSame(1, $dom->query('//li[@data-test="medalha"]//img')->length);
    }

    #[Test]
    public function ordenacaoUsaDataMostradaDepoisIdComFallbackNaConcessao(): void
    {
        $usuario = User::factory()->create();
        $mesa = app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio(), 'Datas');
        $this->medalha($mesa->id, $usuario->identificadorNoDominio(), 1, ['frase' => 'Criacao antiga']);
        $this->medalha($mesa->id, $usuario->identificadorNoDominio(), 2, [
            'frase' => 'Fallback recente', 'partida_criada_em' => null,
            'concedida_em' => '2026-10-04 12:00:00.000',
        ]);
        $this->medalha($mesa->id, $usuario->identificadorNoDominio(), 3, [
            'frase' => 'Maior identidade', 'partida_criada_em' => '2026-10-04 12:00:00.000',
        ]);
        $this->actingAs($usuario)->get('/perfil')->assertOk()
            ->assertSeeInOrder(['Maior identidade', 'Fallback recente', 'Criacao antiga']);
    }

    #[Test]
    public function trofeuTemPlacaLimitadaEDescricaoCompletaForaDaImagem(): void
    {
        $nome = 'Mesa de '.str_repeat('M', 247);
        $html = (string) $this->blade('<x-trofeu tipo="vitoria" :mesa="$nome" data="03/10/2026" />', compact('nome'));
        $dom = $this->dom($html);
        $placa = $dom->query('//*[@data-test="mesa-na-placa"]')->item(0);
        $legenda = $dom->query('//figcaption[@data-test="legenda-do-trofeu"]')->item(0);
        $this->assertNotNull($placa);
        $this->assertNotNull($legenda);
        $this->assertStringContainsString('line-clamp-2', $placa->getAttribute('class'));
        $this->assertSame($nome, trim($legenda->textContent));
        $this->assertSame('figure', $legenda->parentNode->nodeName);
        // A limitação é visual por CSS; o texto completo permanece acessível no DOM.
        $this->assertSame($nome, trim($placa->textContent));
    }

    #[Test]
    public function medeConsultasDoPerfilComCincoParticipacoesReconhecidas(): void
    {
        $usuario = User::factory()->create();
        $plataforma = app(Plataforma::class);
        for ($i = 1; $i <= 5; $i++) {
            $admin = User::factory()->create();
            $mesa = $plataforma->criarMesa($admin->identificadorNoDominio(), "Mesa {$i}");
            $local = $plataforma->cadastrarParticipanteLocal($mesa->id, "Local {$i}", $usuario->email, $admin->identificadorNoDominio());
            $plataforma->reconhecerParticipacaoLocal($local, $usuario->identificadorNoDominio(), $usuario->email);
            $this->medalha($mesa->id, $local, 1);
            DB::table('trofeus')->insert([
                'pessoa' => $local, 'mesa_id' => $mesa->id, 'mesa_nome' => "Mesa {$i}",
                'tipo' => 'vitoria', 'concedido_em' => '2026-10-03 12:00:00.000',
            ]);
        }

        $this->actingAs($usuario)->get('/perfil')->assertOk();
        $pdo = DB::connection()->getPdo();
        $questions = static fn (): int => (int) $pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(\PDO::FETCH_NUM)[1];
        $calibracao = $questions();
        $this->assertSame(1, $questions() - $calibracao);
        $medicoes = [];
        for ($i = 0; $i < 2; $i++) {
            $antes = $questions();
            $resposta = $this->get('/perfil')->assertOk();
            // Desconta o próprio SHOW final; inclui as consultas diretas por PDO.
            $medicoes[] = $questions() - $antes - 1;
            $this->assertCount(5, $resposta->viewData('medalhas'));
            $this->assertCount(5, $resposta->viewData('trofeus'));
        }
        fwrite(STDOUT, 'Questions perfil (5 reconhecidas): '.json_encode($medicoes).PHP_EOL);
        // A página também renderiza a navegação mobile, que consulta o contexto das mesas e os convites de amizade.
        $this->assertSame([14, 14], $medicoes);
    }

    private function medalha(string $mesa, string $pessoa, int $partida, array $dados = []): void
    {
        DB::table('medalhas')->insert(array_replace([
            'pessoa' => $pessoa, 'mesa_id' => $mesa, 'mesa_nome' => 'Medalhas novas',
            'partida' => $partida, 'colocacao' => 1, 'empate' => false, 'jogo' => 'labirinto',
            'frase' => null, 'concedida_em' => '2026-10-05 12:00:00.000',
            'partida_criada_em' => '2026-10-03 01:00:00.000',
        ], $dados));
    }

    private function dom(string $html): DOMXPath
    {
        $anterior = libxml_use_internal_errors(true);
        try {
            $documento = new DOMDocument;
            $documento->loadHTML('<?xml encoding="UTF-8">'.$html);

            return new DOMXPath($documento);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }
}
