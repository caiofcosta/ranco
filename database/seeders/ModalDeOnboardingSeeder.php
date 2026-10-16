<?php

namespace Database\Seeders;

use App\Models\ModalDeNotificacao;
use Illuminate\Database\Seeder;

class ModalDeOnboardingSeeder extends Seeder
{
    public function run(): void
    {
        // Criar a modal de onboarding padrão (especificada na tarefa)
        ModalDeNotificacao::firstOrCreate(
            ['tipo' => 'onboarding', 'titulo' => 'Primeira vez por aqui?'],
            [
                'conteudo' => [
                    'texto_inicial' => 'O RANÇO junta jogos rápidos pra jogar sozinho ou com a galera.',
                    'secoes' => [
                        [
                            'titulo' => 'Mesas',
                            'texto' => 'É onde a turma se reúne pra jogar. Você cria a mesa, chama o pessoal e escolhe os jogos.',
                        ],
                        [
                            'titulo' => 'Agora',
                            'texto' => 'Mostra quando tem alguma coisa esperando por você.',
                        ],
                        [
                            'titulo' => 'Jogando',
                            'texto' => 'Mostra as partidas que já estão rolando.',
                        ],
                    ],
                    'texto_final' => 'Quer jogar com a galera? Crie uma mesa.
Tá sozinho? Escolha um jogo e comece.',
                ],
                'ativo' => true,
            ]
        );
    }
}
