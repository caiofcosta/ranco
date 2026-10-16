<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Opcoes\Lista;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opções iniciais das listas editáveis no Painel. Idempotente: só insere o que ainda não existe
 * (pela lista e pelo valor), sem desfazer o que foi editado ou desativado no Painel.
 * Roda pela migração que cria a tabela e também com `./dev.sh artisan db:seed`.
 */
class OpcoesSeeder extends Seeder
{
    /** @var array<string, list<string>> Lista => rótulos, na ordem de exibição. */
    private const OPCOES = [
        'nomes_de_mesa' => [
            'Família (infelizmente)',
            'Primos do caos',
            'Amizade em teste',
            'A turma de sempre',
            'Sem perdão',
            'Clube do ranço',
            'Rivalidade saudável (dizem)',
            'Revanche eterna',
            'Turma da faculdade',
            'Firma',
            'Grupo do churrasco',
            'Casal em disputa',
            'Domingo em família',
            'Happy hour competitivo',
            'Laboratório de rancor',
            'Ninguém sai daqui com amizade',
        ],
    ];

    public function run(): void
    {
        $agora = now();
        $linhas = [];

        foreach (self::OPCOES as $lista => $rotulos) {
            foreach ($rotulos as $posicao => $rotulo) {
                $linhas[] = [
                    'lista' => Lista::from($lista)->value,
                    'valor' => Str::slug($rotulo),
                    'rotulo' => $rotulo,
                    'ordem' => $posicao + 1,
                    'ativo' => true,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        DB::table('opcoes')->insertOrIgnore($linhas);
    }
}
