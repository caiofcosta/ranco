<?php

declare(strict_types=1);

namespace App\Dominio;

/** Identifica a regra que falhou, para a tela traduzir sem depender do texto da mensagem. */
enum CodigoDaRegra: string
{
    case LimiteDeMesas = 'limite_de_mesas';
    case JogadaEmOutraMesa = 'jogada_em_outra_mesa';
    case JogandoAgora = 'jogando_agora';
    case Invisivel = 'invisivel';

    // Convite de mesa
    case MesaEncerrada = 'mesa_encerrada';
    case SomenteAdminConvida = 'somente_admin_convida';
    case ConviteExigeAmizade = 'convite_exige_amizade';
    case ConvidadoBloqueou = 'convidado_bloqueou';
    case JaEstaNaMesa = 'ja_esta_na_mesa';
    case ConviteJaPendente = 'convite_ja_pendente';
    case ConviteNaoPendente = 'convite_nao_pendente';
    case ConviteDeOutraPessoa = 'convite_de_outra_pessoa';
    case SomenteQuemConvidouCancela = 'somente_quem_convidou_cancela';
}
