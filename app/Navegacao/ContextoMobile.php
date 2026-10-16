<?php

declare(strict_types=1);

namespace App\Navegacao;

use App\Amizade\ConvitesDeAmizade;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Inicio\ConviteNaHome;
use App\Inicio\MesaNaHome;
use App\Mesa\PessoasDaMesa;
use App\Models\User;

/** Projeção da navegação: não muda limites, participação ou andamento das partidas. */
final class ContextoMobile
{
    public function de(User $usuario): array
    {
        $plataforma = app(Plataforma::class);
        $pessoa = $usuario->identificadorNoDominio();
        $mesas = $plataforma->mesasAbertasDe($pessoa);
        $invisivel = $plataforma->estaInvisivel($pessoa);
        $administradas = $participacoes = $pendencias = [];
        $jogando = null;

        foreach ($mesas as $mesa) {
            $resumo = MesaNaHome::de($mesa, $pessoa);
            $partida = $mesa->partidaAtiva();
            $admin = $mesa->admin() === $pessoa;
            $jogaPor = $admin ? [$pessoa, ...array_map(fn ($local) => $local->id, $mesa->participantesLocais())] : [$pessoa];
            $emCurso = $partida && array_any($jogaPor, fn ($id) => $partida->temTentativaEmCurso($id));
            $podeJogar = ! $invisivel && $partida && ! $partida->pausada()
                && array_any($jogaPor, fn ($id) => $partida->podeJogarAgora($id));
            $status = $resumo->rotulo;
            if ($partida && ! $partida->pausada() && $partida->situacao() === SituacaoPartida::EmAndamento && ! $podeJogar) {
                $vez = $partida->vezAtual();
                $status = $vez ? 'Aguardando '.PessoasDaMesa::nome($mesa, $vez).' jogar' : 'Aguardando as outras jogadas';
            }
            $contexto = [
                'mesa' => $resumo,
                'jogo' => $partida ? CatalogoDeJogos::nome($partida->configuracao()->jogo ?? CatalogoDeJogos::LABIRINTO) : null,
                'status' => $podeJogar ? 'Sua vez' : $status,
                'emCurso' => (bool) $emCurso,
            ];
            $participacoes[] = $contexto;
            if ($emCurso) {
                $jogando = $contexto;
            }
            if ($admin) {
                $administradas[] = $contexto;
            }
            if ($podeJogar) {
                $pendencias[] = ['id' => 'jogar-'.$mesa->id, 'tipo' => 'Sua vez', 'nome' => $resumo->nome, 'detalhe' => $resumo->detalhe, 'acao' => 'Jogar agora', 'url' => $resumo->acaoUrl ?? route('mesas.jogar', $mesa->id)];
            }
            if ($admin && ! $invisivel && (! $partida || $partida->situacao() === SituacaoPartida::EmConfiguracao)) {
                $pendencias[] = ['id' => 'admin-'.$mesa->id, 'tipo' => 'Administrador', 'nome' => $resumo->nome, 'detalhe' => ! $partida ? 'Escolha o próximo jogo' : ($mesa->podeIniciarPartida() ? 'A partida pode começar' : 'Revise a configuração e os participantes'), 'acao' => 'Resolver pendência', 'url' => route('mesas.mostrar', $mesa->id)];
            }
        }
        $convites = ConviteNaHome::para($plataforma, $pessoa);
        $pedidos = app(ConvitesDeAmizade::class)->recebidos($usuario);
        // Uma consulta para todos os pedidos, em vez de uma por pedido.
        $quemPediu = $pedidos->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $pedidos->pluck('de'))->get()->keyBy('id');
        $pedidosDeAmizade = $pedidos->map(static function ($pedido) use ($quemPediu) {
                $quem = $quemPediu->get($pedido->de);

                return [
                    'id'   => $pedido->id,
                    'nome' => $quem?->name ?? 'Alguém',
                    'foto' => $quem?->google_avatar_url,
                ];
            })->all();

        return compact('administradas', 'participacoes', 'pendencias', 'jogando', 'convites', 'pedidosDeAmizade', 'invisivel') + [
            'total' => count($pendencias) + count($convites) + count($pedidosDeAmizade),
            'vagas' => $plataforma->vagasDeMesa($pessoa),
            'limite' => Plataforma::LIMITE_DE_MESAS,
        ];
    }
}
