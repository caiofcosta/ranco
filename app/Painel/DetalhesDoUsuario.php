<?php

declare(strict_types=1);

namespace App\Painel;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DetalhesDoUsuario
{
    private const FUSO = 'America/Sao_Paulo';

    private const TENTATIVAS_SOLO = [
        ['tabela' => 'tentativas_solo_labirinto', 'jogo' => CatalogoDeJogos::LABIRINTO, 'resultado' => 'tempo'],
    ];

    /** @return array<string, mixed> */
    public function consultar(User $usuario): array
    {
        $id = (string) $usuario->id;
        $amizades = $this->amizades($id);
        $mesas = $this->mesas($id);

        return [
            'conta' => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'criadaEm' => $this->data($usuario->created_at?->toISOString()),
                'emailConfirmadoEm' => $this->data($usuario->email_verified_at?->toISOString()),
                'invisivel' => (bool) $usuario->invisivel,
                'bloqueadoEm' => $this->data($usuario->bloqueado_em?->toISOString()),
                'vistaEm' => $this->data($usuario->getAttribute('visto_em')),
                'senhaConfigurada' => $usuario->temSenha(),
                'googleVinculado' => $usuario->google_id !== null,
                'autenticacaoEmDoisFatores' => $usuario->two_factor_confirmed_at !== null,
            ],
            'acessos' => $this->acessos($usuario->id),
            'sessoes' => $this->sessoes($usuario->id),
            'aceites' => $this->aceites($usuario->id),
            'amizades' => $amizades,
            'convitesAmizade' => $this->convitesAmizade($id),
            'convitesMesa' => $this->convitesMesa($id),
            'mesas' => $mesas,
            'partidasEmMesa' => $this->partidasEmMesa($id),
            'jogosSolo' => $this->jogosSolo($usuario->id),
            'medalhas' => $this->medalhas($id),
            'trofeus' => $this->trofeus($id),
        ];
    }

    /** @return array<string, mixed> */
    private function amizades(string $id): array
    {
        $amizades = DB::table('amizades')
            ->where('usuario_a', $id)
            ->orWhere('usuario_b', $id)
            ->get(['usuario_a', 'usuario_b', 'criada_em']);
        $amigos = [];
        foreach ($amizades as $amizade) {
            $outro = (string) ($amizade->usuario_a === $id ? $amizade->usuario_b : $amizade->usuario_a);
            $amigos[$outro] = ['id' => $outro, 'criadaEm' => $this->data($amizade->criada_em)];
        }
        $this->anexarPessoas($amigos);

        return [
            'amigos' => array_values($amigos),
            'pedidosEnviados' => $this->relacoesComPessoas(
                DB::table('pedidos_amizade')->where('de', $id)->get(['para as pessoa', 'criado_em']),
                'criado_em',
            ),
            'pedidosRecebidos' => $this->relacoesComPessoas(
                DB::table('pedidos_amizade')->where('para', $id)->get(['de as pessoa', 'criado_em']),
                'criado_em',
            ),
            'bloqueadosPorConta' => $this->relacoesComPessoas(
                DB::table('bloqueios')->where('quem', $id)->get(['alvo as pessoa', 'criado_em']),
                'criado_em',
            ),
            'bloquearamConta' => $this->relacoesComPessoas(
                DB::table('bloqueios')->where('alvo', $id)->get(['quem as pessoa', 'criado_em']),
                'criado_em',
            ),
        ];
    }

    /** @return list<array{versao: string, aceitoEm: ?string}> */
    private function aceites(int $id): array
    {
        return DB::table('aceites')
            ->where('user_id', $id)
            ->orderByDesc('aceito_em')
            ->get(['versao', 'aceito_em'])
            ->map(fn (stdClass $aceite): array => [
                'versao' => $aceite->versao,
                'aceitoEm' => $this->data($aceite->aceito_em),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function convitesAmizade(string $id): array
    {
        $linhas = DB::table('convites_de_amizade')
            ->where(static function ($consulta) use ($id): void {
                $consulta->where('de', $id)->orWhere('para', $id);
            })
            ->orderByDesc('created_at')
            ->get(['de', 'para', 'created_at', 'expira_em', 'aceito_em', 'recusado_em']);
        $ids = $linhas->map(static fn (stdClass $convite): ?string => $convite->de === $id
            ? ($convite->para === null ? null : (string) $convite->para)
            : (string) $convite->de)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $pessoas = User::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy(static fn (User $pessoa): string => (string) $pessoa->id);

        return $linhas->map(function (stdClass $convite) use ($id, $pessoas): array {
            $outraPessoa = $convite->de === $id ? $convite->para : $convite->de;

            return [
                'direcao' => $convite->de === $id ? 'Enviado' : 'Recebido',
                'outraPessoa' => $outraPessoa === null
                    ? 'Conta removida'
                    : ($pessoas->get((string) $outraPessoa)?->name ?? 'Conta indisponível'),
                'criadoEm' => $this->data($convite->created_at),
                'situacao' => $convite->aceito_em !== null
                    ? 'Aceito'
                    : ($convite->recusado_em !== null
                        ? 'Recusado'
                        : (Carbon::parse($convite->expira_em, 'UTC')->isPast() ? 'Expirado' : 'Pendente')),
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function convitesMesa(string $id): array
    {
        $linhas = DB::table('convites')
            ->join('mesas', 'mesas.id', '=', 'convites.mesa_id')
            ->where(static function ($consulta) use ($id): void {
                $consulta->where('convites.de', $id)->orWhere('convites.para', $id);
            })
            ->orderByDesc('convites.criado_em')
            ->get([
                'convites.de',
                'convites.para',
                'convites.situacao',
                'convites.criado_em',
                'mesas.nome',
                'mesas.admin',
            ]);
        $ids = $linhas->flatMap(static fn (stdClass $convite): array => [
            (string) ($convite->de === $id ? $convite->para : $convite->de),
            (string) $convite->admin,
        ])
            ->unique()
            ->values()
            ->all();
        $pessoas = User::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy(static fn (User $pessoa): string => (string) $pessoa->id);

        return $linhas->map(function (stdClass $convite) use ($id, $pessoas): array {
            $outroId = (string) ($convite->de === $id ? $convite->para : $convite->de);

            return [
                'direcao' => $convite->de === $id ? 'Enviado' : 'Recebido',
                'outraPessoa' => $pessoas->get($outroId)?->name ?? 'Conta indisponível',
                'mesa' => filled($convite->nome) ? $convite->nome : 'Mesa de '.($pessoas->get((string) $convite->admin)?->name ?? 'conta indisponível'),
                'situacao' => $convite->situacao,
                'criadoEm' => $this->data($convite->criado_em),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, stdClass>  $linhas
     * @return list<array<string, mixed>>
     */
    private function relacoesComPessoas(Collection $linhas, string $campoData): array
    {
        $pessoas = [];
        foreach ($linhas as $linha) {
            $id = (string) $linha->pessoa;
            $pessoas[$id] = ['id' => $id, 'criadaEm' => $this->data($linha->{$campoData})];
        }
        $this->anexarPessoas($pessoas);

        return array_values($pessoas);
    }

    /** @param array<string, array<string, mixed>> $relacoes */
    private function anexarPessoas(array &$relacoes): void
    {
        if ($relacoes === []) {
            return;
        }

        $pessoas = User::query()
            ->whereIn('id', array_keys($relacoes))
            ->get(['id', 'name', 'email'])
            ->keyBy(static fn (User $usuario): string => (string) $usuario->id);

        foreach ($relacoes as $id => &$relacao) {
            $pessoa = $pessoas->get($id);
            $relacao['nome'] = $pessoa?->name ?? 'Conta indisponível';
            $relacao['email'] = $pessoa?->email;
        }
        unset($relacao);
    }

    /** @return list<array<string, mixed>> */
    private function mesas(string $id): array
    {
        $porId = [];
        $participacoes = DB::table('mesas')
            ->leftJoin('membros_mesa', static function ($join) use ($id): void {
                $join->on('membros_mesa.mesa_id', '=', 'mesas.id')
                    ->where('membros_mesa.usuario', '=', $id);
            })
            ->leftJoin('users as administrador', 'administrador.id', '=', DB::raw('CAST(mesas.admin AS UNSIGNED)'))
            ->where(static function ($consulta) use ($id): void {
                $consulta->where('mesas.admin', $id)->orWhere('membros_mesa.usuario', $id);
            })
            ->orderByDesc('mesas.atualizada_em')
            ->get([
                'mesas.id',
                'mesas.nome',
                'mesas.admin',
                'mesas.situacao',
                'mesas.criada_em',
                'mesas.atualizada_em',
                'administrador.name as nome_admin',
                'membros_mesa.situacao as situacao_membro',
            ]);

        foreach ($participacoes as $mesa) {
            $porId[$mesa->id] = [
                'id' => $mesa->id,
                'nome' => filled($mesa->nome) ? $mesa->nome : (filled($mesa->nome_admin) ? 'Mesa de '.$mesa->nome_admin : 'Mesa'),
                'situacao' => $mesa->situacao,
                'participacao' => (string) $mesa->admin === $id ? 'Admin da mesa' : 'Membro: '.($mesa->situacao_membro ?? 'histórico'),
                'criadaEm' => $this->data($mesa->criada_em),
                'atualizadaEm' => $this->data($mesa->atualizada_em),
            ];
        }

        $locais = DB::table('participantes_locais')
            ->join('mesas', 'mesas.id', '=', 'participantes_locais.mesa_id')
            ->leftJoin('users as administrador', 'administrador.id', '=', DB::raw('CAST(mesas.admin AS UNSIGNED)'))
            ->where('participantes_locais.usuario', $id)
            ->orderByDesc('mesas.atualizada_em')
            ->get([
                'mesas.id',
                'mesas.nome',
                'mesas.admin',
                'mesas.situacao',
                'mesas.criada_em',
                'mesas.atualizada_em',
                'administrador.name as nome_admin',
                'participantes_locais.situacao as situacao_local',
            ]);

        foreach ($locais as $mesa) {
            $porId[$mesa->id] ??= [
                'id' => $mesa->id,
                'nome' => filled($mesa->nome) ? $mesa->nome : (filled($mesa->nome_admin) ? 'Mesa de '.$mesa->nome_admin : 'Mesa'),
                'situacao' => $mesa->situacao,
                'participacao' => '',
                'criadaEm' => $this->data($mesa->criada_em),
                'atualizadaEm' => $this->data($mesa->atualizada_em),
            ];
            $local = 'Participante local: '.$mesa->situacao_local;
            $porId[$mesa->id]['participacao'] = trim($porId[$mesa->id]['participacao'].' · '.$local, ' ·');
        }

        return array_values($porId);
    }

    /** @return array{total: int, ultimaEm: ?string} */
    private function partidasEmMesa(string $id): array
    {
        $partidas = DB::table('eventos_partida')
            ->where('participante', $id)
            ->select('mesa_id', 'partida')
            ->selectRaw('MAX(ocorrido_em) as ultima_atividade')
            ->groupBy('mesa_id', 'partida')
            ->orderByDesc('ultima_atividade')
            ->get();

        return [
            'total' => $partidas->count(),
            'ultimaEm' => $this->data($partidas->first()?->ultima_atividade),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function jogosSolo(int $id): array
    {
        $jogos = [];
        foreach (self::TENTATIVAS_SOLO as $definicao) {
            $tabela = $definicao['tabela'];
            $total = DB::table($tabela)->where('pessoa_id', $id)->count();
            $recentes = DB::table($tabela)
                ->where('pessoa_id', $id)
                ->orderByDesc('iniciada_em')
                ->limit(5)
                ->get(['iniciada_em', 'concluida_em', 'recorde', $definicao['resultado']]);

            $jogos[] = [
                'nome' => CatalogoDeJogos::nome($definicao['jogo']),
                'total' => $total,
                'recentes' => $recentes->map(fn (stdClass $tentativa): array => [
                    'iniciadaEm' => $this->data($tentativa->iniciada_em),
                    'concluidaEm' => $this->data($tentativa->concluida_em),
                    'resultado' => $this->resultadoSolo($definicao['resultado'], $tentativa->{$definicao['resultado']}),
                    'recorde' => (bool) $tentativa->recorde,
                ])->all(),
            ];
        }

        return $jogos;
    }

    /** @return list<array<string, mixed>> */
    private function medalhas(string $id): array
    {
        return DB::table('medalhas')
            ->where('pessoa', $id)
            ->orderByRaw('COALESCE(partida_criada_em, concedida_em) DESC')
            ->orderByDesc('id')
            ->get(['jogo', 'colocacao', 'empate', 'mesa_nome', 'partida_criada_em', 'concedida_em'])
            ->map(fn (stdClass $medalha): array => [
                'jogo' => CatalogoDeJogos::nome($medalha->jogo),
                'colocacao' => (int) $medalha->colocacao,
                'empate' => (bool) $medalha->empate,
                'mesa' => $medalha->mesa_nome,
                'data' => $this->data($medalha->partida_criada_em ?? $medalha->concedida_em),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function trofeus(string $id): array
    {
        return DB::table('trofeus')
            ->where('pessoa', $id)
            ->orderByRaw('COALESCE(mesa_criada_em, concedido_em) DESC')
            ->orderByDesc('id')
            ->get(['tipo', 'mesa_nome', 'mesa_criada_em', 'concedido_em'])
            ->map(fn (stdClass $trofeu): array => [
                'tipo' => $trofeu->tipo,
                'mesa' => $trofeu->mesa_nome,
                'data' => $this->data($trofeu->mesa_criada_em ?? $trofeu->concedido_em),
            ])
            ->all();
    }

    /** @return list<string> */
    private function acessos(int $id): array
    {
        return DB::table('registros_de_acesso')
            ->where('user_id', $id)
            ->orderByDesc('ocorrido_em')
            ->limit(20)
            ->pluck('ocorrido_em')
            ->map(fn (string $data): string => $this->data($data) ?? '')
            ->all();
    }

    /** @return list<string> */
    private function sessoes(int $id): array
    {
        return DB::table('sessions')
            ->where('user_id', $id)
            ->orderByDesc('last_activity')
            ->limit(10)
            ->pluck('last_activity')
            ->map(fn (int $instante): string => $this->data(gmdate('Y-m-d H:i:s', $instante)) ?? '')
            ->all();
    }

    private function resultadoSolo(string $campo, mixed $resultado): string
    {
        if ($resultado === null) {
            return 'Sem resultado';
        }

        return match ($campo) {
            'tempo' => $resultado.' s',
            'pontuacao' => $resultado.' pontos',
            'diferenca_ms' => $resultado.' ms de diferença',
            default => 'Resultado registrado',
        };
    }

    private function data(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return Carbon::parse($valor, 'UTC')
            ->setTimezone(self::FUSO)
            ->format('d/m/Y H:i');
    }
}
