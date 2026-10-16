<?php

declare(strict_types=1);

namespace App\Convite;

use App\Dominio\CodigoDaRegra;
use App\Dominio\RegraViolada;

/** Traduz a regra do domínio para um fato na tela, pelo código da regra e sem o identificador interno. */
final class MensagemDoConvite
{
    public static function de(RegraViolada $erro): string
    {
        return match ($erro->codigo) {
            CodigoDaRegra::ConviteDeOutraPessoa => 'Este convite não é para esta conta.',
            CodigoDaRegra::MesaEncerrada => 'A mesa do convite foi encerrada.',
            CodigoDaRegra::SomenteAdminConvida => 'Somente o admin convida para a mesa.',
            CodigoDaRegra::ConviteExigeAmizade => 'O convite exige amizade aceita.',
            CodigoDaRegra::JaEstaNaMesa => 'Essa pessoa já está na mesa.',
            CodigoDaRegra::ConviteJaPendente => 'Essa pessoa já tem um convite pendente para esta mesa.',
            CodigoDaRegra::SomenteQuemConvidouCancela => 'Somente quem convidou pode cancelar o convite.',
            // A frase do domínio já é a da tela.
            CodigoDaRegra::LimiteDeMesas, CodigoDaRegra::Invisivel => $erro->getMessage(),
            // Bloqueio não é revelado: aparece como convite indisponível.
            default => 'Este convite não está mais pendente.',
        };
    }
}
