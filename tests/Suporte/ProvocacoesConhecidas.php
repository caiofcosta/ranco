<?php

declare(strict_types=1);

namespace Tests\Suporte;

use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Provocacao;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;
use App\Infra\Memoria\RepositorioDeProvocacoesEmMemoria;

/** Troca as frases do banco por uma frase conhecida por momento e intensidade, para os testes saberem o que esperar. */
trait ProvocacoesConhecidas
{
    private function usarProvocacoesConhecidas(): void
    {
        $this->app->instance(RepositorioDeProvocacoes::class, new RepositorioDeProvocacoesEmMemoria(
            [
                new Provocacao(1, 'Erro leve.', 1, ['acesso']),
                new Provocacao(2, 'Erro médio.', 2, ['acesso']),
                new Provocacao(3, 'Erro número {tentativa}.', 3, ['acesso']),
                new Provocacao(4, 'Pausa de {minutos} minutos.', 2, ['castigo']),
                new Provocacao(5, 'Calma: {minutos} minutos.', 2, ['links']),
                new Provocacao(6, 'Sua vez.', 2, ['vez_pendente']),
                new Provocacao(7, 'Convite de {admin}.', 2, ['convite_pendente']),
                new Provocacao(8, 'Partida pronta.', 2, ['partida_pronta']),
                new Provocacao(9, 'Vitória sua.', 1, ['vitoria_recente']),
                new Provocacao(10, 'Derrota para {vencedor}.', 1, ['derrota_recente']),
                new Provocacao(11, 'Mesa sozinha.', 1, ['mesa_aguardando']),
                new Provocacao(12, 'Sem mesa.', 1, ['vazio']),
                new Provocacao(13, 'Sem novidade.', 1, ['sem_novidade']),
                new Provocacao(14, 'Eu não me meteria. Mas o convite já foi enviado.', 1, ['convite_email']),
            ],
            [
                Momento::ErroDeLogin->value => ['acesso'],
                Momento::PausaDeLogin->value => ['castigo'],
                Momento::LimiteDeEmails->value => ['links'],
                Momento::VezPendente->value => ['vez_pendente'],
                Momento::ConvitePendente->value => ['convite_pendente'],
                Momento::PartidaPronta->value => ['partida_pronta'],
                Momento::VitoriaRecente->value => ['vitoria_recente'],
                Momento::DerrotaRecente->value => ['derrota_recente'],
                Momento::MesaAguardando->value => ['mesa_aguardando'],
                Momento::SemMesa->value => ['vazio'],
                Momento::SemNovidade->value => ['sem_novidade'],
                Momento::ConvitePorEmail->value => ['convite_email'],
            ],
        ));
    }
}
