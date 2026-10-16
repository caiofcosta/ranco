<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{texto: string, tema: string, intensidade: int}> */
    private const FRASES = [
        ['texto' => 'Venceu. A mesa inteira vai lembrar disso, e você também.', 'tema' => 'vitoria', 'intensidade' => 1],
        ['texto' => 'Ganhou o jogo. Agora precisa aguentar a mesa comemorando.', 'tema' => 'vitoria', 'intensidade' => 1],
        ['texto' => 'Quase. {vencedor} ficou com o ouro, e você com a certeza de que foi perto.', 'tema' => 'quase', 'intensidade' => 2],
        ['texto' => 'Segundo lugar. Faltou pouco, e {vencedor} vai lembrar que foi justo.', 'tema' => 'quase', 'intensidade' => 2],
        ['texto' => 'Prata. Perto o bastante para incomodar, longe o bastante para não levar nada além disso.', 'tema' => 'quase', 'intensidade' => 2],
        ['texto' => 'Terceiro lugar. Pelo menos alguém ficou atrás de você.', 'tema' => 'terceiro', 'intensidade' => 1],
        ['texto' => 'Bronze. Segue o jogo, e o pódio também.', 'tema' => 'terceiro', 'intensidade' => 1],
        ['texto' => 'Terceiro lugar: nem perto, nem longe. Só presente.', 'tema' => 'terceiro', 'intensidade' => 1],
        ['texto' => 'Empate no topo com {outro}. A mesa vai precisar de uma explicação.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Dividiram o ouro com {outro}. Ninguém sai limpo disso.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Você e {outro} no topo, cada um convencido de que foi habilidade.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Prata dividida com {outro}. Nenhum dos dois vai aceitar sem reclamar.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Você e {outro} no segundo lugar. Os dois acham que deveriam ter ganhado.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Empataram com {outro}. Ninguém ficou com a glória, só com a prata.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Bronze dividido com {outro}. Os dois estão igualmente insatisfeitos.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Você e {outro} no mesmo bronze. Pelo menos estão no mesmo lugar.', 'tema' => 'empate', 'intensidade' => 1],
        ['texto' => 'Terceiro lugar empatado com {outro}. Dividiram o que ninguém quis.', 'tema' => 'empate', 'intensidade' => 1],
    ];

    public function up(): void
    {
        $this->exigirVariavel('partida.segundo_lugar', 'vencedor');
        foreach ([
            'partida.primeiro_lugar_empate',
            'partida.segundo_lugar_empate',
            'partida.terceiro_lugar_empate',
        ] as $momento) {
            $this->exigirVariavel($momento, 'outro');
        }

        DB::table('temas_de_provocacao')->insertOrIgnore([
            ['codigo' => 'terceiro', 'tipo' => 'generico', 'significado' => 'Terminou em terceiro lugar'],
            ['codigo' => 'empate', 'tipo' => 'generico', 'significado' => 'Empatou a colocação e divide a medalha'],
        ]);
        DB::table('momentos_temas')->insertOrIgnore([
            ['momento' => 'partida.terceiro_lugar', 'tema' => 'terceiro'],
            ['momento' => 'partida.primeiro_lugar_empate', 'tema' => 'empate'],
            ['momento' => 'partida.segundo_lugar_empate', 'tema' => 'empate'],
            ['momento' => 'partida.terceiro_lugar_empate', 'tema' => 'empate'],
        ]);

        foreach (self::FRASES as $frase) {
            $id = DB::table('provocacoes')->where('texto', $frase['texto'])->value('id');
            if ($id === null) {
                $id = DB::table('provocacoes')->insertGetId([
                    'texto' => $frase['texto'],
                    'intensidade' => $frase['intensidade'],
                    'perspectiva' => 'pessoa',
                ]);
            }
            DB::table('provocacoes_temas')->insertOrIgnore([
                'provocacao_id' => $id,
                'tema' => $frase['tema'],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('provocacoes')->whereIn('texto', array_column(self::FRASES, 'texto'))->delete();
        DB::table('temas_de_provocacao')->whereIn('codigo', ['terceiro', 'empate'])->delete();
    }

    private function exigirVariavel(string $momento, string $variavel): void
    {
        $oferecidas = array_filter(array_map(
            static fn (string $nome): string => trim($nome),
            explode(',', (string) DB::table('momentos_de_provocacao')->where('codigo', $momento)->value('variaveis')),
        ));
        if (!in_array($variavel, $oferecidas, true)) {
            throw new \RuntimeException("O momento {$momento} não oferece {{$variavel}}.");
        }
    }
};
