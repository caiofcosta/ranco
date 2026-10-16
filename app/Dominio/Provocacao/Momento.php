<?php

declare(strict_types=1);

namespace App\Dominio\Provocacao;

/**
 * Lugares do sistema onde pode aparecer uma provocação. Cada momento aceita alguns temas
 * (tabela momentos_temas) e oferece algumas variáveis para as frases.
 * A descrição completa está na nota "RANÇO - Provocações".
 */
enum Momento: string
{
    // Conta
    case ErroDeLogin = 'conta.erro_de_login';
    case PausaDeLogin = 'conta.pausa_de_login';
    case LimiteDeEmails = 'conta.limite_de_emails';

    // Perfil e amizades
    case PerfilPrivado = 'perfil.privado';
    case AmizadeVazia = 'amizade.vazia';

    // Mesa, e-mail e tela inicial
    case ConviteRecebido = 'mesa.convite_recebido';
    case ConvitePorEmail = 'email.convite';
    case VezPendente = 'inicio.vez_pendente';
    case ConvitePendente = 'inicio.convite_pendente';
    case PartidaPronta = 'inicio.partida_pronta';
    case VitoriaRecente = 'inicio.vitoria_recente';
    case DerrotaRecente = 'inicio.derrota_recente';
    case MesaAguardando = 'inicio.mesa_aguardando';
    case SemMesa = 'inicio.sem_mesa';
    case SemNovidade = 'inicio.sem_novidade';

    // Jogo solo
    case SoloMelhorTempo = 'solo.melhor_tempo';
    case SoloSemRecorde = 'solo.sem_recorde';

    // Partida
    case PartidaIniciada = 'partida.iniciada';
    case PausadaPeloAdmin = 'partida.pausada_pelo_admin';
    case Retorno = 'partida.retorno';
    case VezPuladaPorPrazo = 'partida.vez_pulada_por_prazo';
    case VezPuladaPeloAdmin = 'partida.vez_pulada_pelo_admin';
    case TentativaInterrompida = 'partida.tentativa_interrompida';
    case AbandonouAPartida = 'partida.abandonou';
    case RemovidoDaPartida = 'partida.removido';
    case UltimoDaRodada = 'partida.ultimo_da_rodada';
    case DesempatePerdido = 'partida.desempate_perdido';
    case PausadaPorFaltaDeJogadas = 'partida.pausada_pelo_sistema';
    case Vitoria = 'partida.vitoria';
    case SegundoLugar = 'partida.segundo_lugar';
    case TerceiroLugar = 'partida.terceiro_lugar';
    case PrimeiroLugarEmpate = 'partida.primeiro_lugar_empate';
    case SegundoLugarEmpate = 'partida.segundo_lugar_empate';
    case TerceiroLugarEmpate = 'partida.terceiro_lugar_empate';
    case Derrota = 'partida.derrota';
    case VitoriaPorDesistencia = 'partida.vitoria_por_desistencia';

    // Troféu da mesa
    case TrofeuVitoria = 'mesa.trofeu_vitoria';
    case TrofeuEmpate = 'mesa.trofeu_empate';
    case TrofeuSegundo = 'mesa.trofeu_segundo';
    case TrofeuTerceiro = 'mesa.trofeu_terceiro';
    case TrofeuSegundoEmpate = 'mesa.trofeu_segundo_empate';
    case TrofeuTerceiroEmpate = 'mesa.trofeu_terceiro_empate';
}
