<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function momentos(): array
    {
        return [
            'melhor_tempo' => ['Melhor tempo no jogo solo, incluindo o primeiro', [
                'O melhor tempo é seu. A cobrança na próxima também.',
                'Tempo de referência registrado. Agora existe algo para superar.',
                'O cronômetro ganhou uma marca para defender.',
            ]],
            'sem_recorde' => ['Jogo solo concluído sem superar o melhor tempo', [
                'O recorde continua confortável no lugar dele.',
                'A chegada veio. O recorde preferiu ficar onde estava.',
                'O melhor tempo sobreviveu a mais uma tentativa.',
            ]],
        ];
    }

    public function up(): void
    {
        foreach ($this->momentos() as $sufixo => [$descricao, $frases]) {
            $momento = 'solo.'.$sufixo;
            $tema = 'solo_'.$sufixo;
            DB::table('temas_de_provocacao')->insert(['codigo' => $tema, 'tipo' => 'contexto', 'significado' => $descricao]);
            DB::table('momentos_de_provocacao')->insert(['codigo' => $momento, 'descricao' => $descricao, 'variaveis' => '']);
            DB::table('momentos_temas')->insert(['momento' => $momento, 'tema' => $tema]);
            foreach ($frases as $frase) {
                $id = DB::table('provocacoes')->insertGetId(['texto' => $frase, 'intensidade' => 1, 'perspectiva' => 'pessoa']);
                DB::table('provocacoes_temas')->insert(['provocacao_id' => $id, 'tema' => $tema]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->momentos() as $sufixo => [$descricao, $frases]) {
            DB::table('provocacoes')->whereIn('texto', $frases)->delete();
            DB::table('momentos_de_provocacao')->where('codigo', 'solo.'.$sufixo)->delete();
            DB::table('temas_de_provocacao')->where('codigo', 'solo_'.$sufixo)->delete();
        }
    }
};
