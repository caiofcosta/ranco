<?php

declare(strict_types=1);

namespace App\Painel;

use App\Amizade\AmigosOnline;
use App\Dominio\Relogio;
use App\Infra\Banco\FormatoDeData;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Números do dashboard do Painel (/painel). Só leitura, só colunas e projeções: o `estado`
 * serializado da mesa nunca é desserializado aqui.
 *
 * O banco guarda UTC. "Hoje" e os dias do histórico são os de Brasília: os limites são calculados
 * em PHP, convertidos para UTC e usados na consulta (sem CONVERT_TZ).
 */
final class ResumoDoPainel
{
    public const FUSO = 'America/Sao_Paulo';

    public const DIAS_DO_HISTORICO = 7;

    /** Mesa aberta sem nenhuma gravação (entrada, jogada, renomeação…) há mais que isso pode estar inativa. */
    public const MINUTOS_PARA_MESA_INATIVA = 15;

    private readonly DateTimeZone $brasilia;

    private readonly DateTimeZone $utc;

    public function __construct(private readonly Relogio $relogio)
    {
        $this->brasilia = new DateTimeZone(self::FUSO);
        $this->utc = new DateTimeZone('UTC');
    }

    /** Todas as contas: inclui administradores e e-mail ainda não confirmado. */
    public function usuariosCadastrados(): int
    {
        return User::query()->count();
    }

    public function novosUsuariosHoje(): int
    {
        [$inicio, $fim] = $this->limitesDeHoje();

        return User::query()->where('created_at', '>=', $inicio)->where('created_at', '<', $fim)->count();
    }

    /**
     * Mesmo critério do "online" entre amigos (`visto_em` dentro da janela do heartbeat). Conta também
     * quem está invisível: a invisibilidade é social e não esconde a pessoa da estatística agregada.
     */
    public function onlineAgora(): int
    {
        return User::query()->where('visto_em', '>=', $this->limiteDeOnline())->count();
    }

    public function mesasAbertas(): int
    {
        return DB::table('mesas')->where('situacao', 'aberta')->count();
    }

    /**
     * Aberta, sem partida em andamento, sem gravação recente e sem nenhum membro ativo online.
     * Partida pausada tem `partida_em_andamento = 1` e, por decisão da primeira versão, nunca é inativa.
     * `usuario_ativo` só é preenchido para contas reais (o participante local fica de fora).
     */
    public function mesasInativas(): int
    {
        $semGravacaoDesde = $this->emUtc($this->relogio->agora()->modify('-'.self::MINUTOS_PARA_MESA_INATIVA.' minutes'));
        $onlineDesde = $this->limiteDeOnline();

        return DB::table('mesas')
            ->where('situacao', 'aberta')
            ->where('partida_em_andamento', 0)
            ->where('atualizada_em', '<', $semGravacaoDesde)
            ->whereNotExists(static function (Builder $membroOnline) use ($onlineDesde): void {
                $membroOnline->selectRaw('1')
                    ->from('membros_mesa')
                    ->join('users', 'users.id', '=', DB::raw('CAST(membros_mesa.usuario_ativo AS UNSIGNED)'))
                    ->whereColumn('membros_mesa.mesa_id', 'mesas.id')
                    ->whereNotNull('membros_mesa.usuario_ativo')
                    ->where('users.visto_em', '>=', $onlineDesde);
            })
            ->count();
    }

    /** Só conta mesa com `criada_em`: as antigas sem data confiável ficam de fora. */
    public function mesasCriadasHoje(): int
    {
        [$inicio, $fim] = $this->limitesDeHoje();

        return DB::table('mesas')->where('criada_em', '>=', $inicio)->where('criada_em', '<', $fim)->count();
    }

    public function mesasFinalizadasHoje(): int
    {
        [$inicio, $fim] = $this->limitesDeHoje();

        return DB::table('mesas')
            ->where('situacao', 'encerrada')
            ->where('encerrada_em', '>=', $inicio)
            ->where('encerrada_em', '<', $fim)
            ->count();
    }

    /**
     * Do dia mais antigo para hoje, sempre com 7 linhas (dia sem ocorrência vale zero). Os registros
     * vêm em UTC e são agrupados aqui, em PHP, pelo dia de Brasília.
     *
     * @return list<array{data: string, rotulo: string, usuarios: int, mesas: int}>
     */
    public function ultimosSeteDias(): array
    {
        $hoje = $this->inicioDeHoje();

        $dias = [];
        for ($atras = self::DIAS_DO_HISTORICO - 1; $atras >= 0; $atras--) {
            $dia = $hoje->modify("-{$atras} days");
            $dias[$dia->format('Y-m-d')] = [
                'data' => $dia->format('Y-m-d'),
                'rotulo' => $dia->format('d/m'),
                'usuarios' => 0,
                'mesas' => 0,
            ];
        }

        $inicio = $this->emUtc($hoje->modify('-'.(self::DIAS_DO_HISTORICO - 1).' days'));
        $fim = $this->emUtc($hoje->modify('+1 day'));

        $this->contarPorDia($dias, 'usuarios', DB::table('users')
            ->where('created_at', '>=', $inicio)->where('created_at', '<', $fim)->pluck('created_at')->all());
        $this->contarPorDia($dias, 'mesas', DB::table('mesas')
            ->where('criada_em', '>=', $inicio)->where('criada_em', '<', $fim)->pluck('criada_em')->all());

        return array_values($dias);
    }

    /**
     * @param array<string, array{data: string, rotulo: string, usuarios: int, mesas: int}> $dias
     * @param list<string> $instantesEmUtc
     */
    private function contarPorDia(array &$dias, string $campo, array $instantesEmUtc): void
    {
        foreach ($instantesEmUtc as $instante) {
            $dia = (new DateTimeImmutable((string) $instante, $this->utc))->setTimezone($this->brasilia)->format('Y-m-d');
            if (isset($dias[$dia])) {
                $dias[$dia][$campo]++;
            }
        }
    }

    private function inicioDeHoje(): DateTimeImmutable
    {
        return $this->relogio->agora()->setTimezone($this->brasilia)->setTime(0, 0);
    }

    /** @return array{0: string, 1: string} Início do dia de Brasília e do dia seguinte, em UTC. */
    private function limitesDeHoje(): array
    {
        $inicio = $this->inicioDeHoje();

        return [$this->emUtc($inicio), $this->emUtc($inicio->modify('+1 day'))];
    }

    private function limiteDeOnline(): string
    {
        return $this->emUtc($this->relogio->agora()->modify('-'.AmigosOnline::JANELA_SEGUNDOS.' seconds'));
    }

    private function emUtc(DateTimeImmutable $instante): string
    {
        return $instante->setTimezone($this->utc)->format(FormatoDeData::PADRAO);
    }
}
