<?php

declare(strict_types=1);

namespace App\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Partida\Criterio;
use App\Dominio\Partida\Tentativa;
use App\Dominio\Relogio;

/** Projeção da partida: apresenta consultas do domínio, sem iniciar tentativas. */
final readonly class TelaDoJogo
{
    /**
     * @param list<array{id: string, nome: string, primeiroNome: string, iniciais: string, local: bool, atual: bool, estado: string, resultado: ?string}> $ordem
     * @param array{rodada: int, alvo: int, resultados: list<array{nome: string, tempoMedido: ?int, diferenca: int, posicao: int}>}|null $resultadosEntreRodadas
     */
    public function __construct(
        public int $numero,
        public string $fase,
        public string $chave,
        public PartidaNaMesa $partida,
        public array $ordem,
        public ?string $liberaEm = null,
        public ?string $prazoExpiraEm = null,
        public ?string $agoraEm = null,
        public ?string $motivoPausa = null,
        public ?array $resultadosEntreRodadas = null,
    ) {}

    public static function de(Mesa $mesa, string $usuario, ?int $numero = null): ?self
    {
        $numero ??= count($mesa->partidas());
        $partida = $mesa->partidas()[$numero - 1] ?? null;
        if ($partida === null || $partida->situacao() === SituacaoPartida::EmConfiguracao) {
            return null;
        }

        $resumo = PartidaNaMesa::de($mesa, $usuario, $partida);
        $participante = null;
        $fase = 'aguardar';
        if ($partida->situacao() === SituacaoPartida::Encerrada) {
            $fase = 'fim';
        } else {
            $participante = $mesa->porQuemJoga($usuario);
            $tentativa = $participante === null ? null : $partida->tentativaDe($partida->rodadaAtual(), $participante, $partida->desempateAtual());
            if ($tentativa?->situacao === SituacaoTentativa::EmAndamento) {
                $fase = 'jogar';
            } elseif ($participante !== null && $tentativa === null && ! $partida->pausada()) {
                $fase = 'iniciar';
            } elseif ($resumo->urlAssistir !== null) {
                $fase = 'assistir';
                $participante = $partida->participanteJogando();
            } elseif ($partida->entreRodadas()) {
                $fase = 'entre-rodadas';
            }
        }

        $pendentes = $partida->pendentesDaRodada();
        $ordem = [];
        foreach (array_unique([...$partida->ordem(), ...$partida->participantesDaRodada($partida->rodadaAtual()), ...$partida->aguardandoProximaRodada()]) as $id) {
            $tentativa = $partida->tentativaDe($partida->rodadaAtual(), $id, $partida->desempateAtual());
            $estado = match (true) {
                $tentativa?->situacao === SituacaoTentativa::Concluida => 'Já jogou',
                $tentativa?->situacao === SituacaoTentativa::EmAndamento => 'Jogando',
                $tentativa?->situacao === SituacaoTentativa::Interrompida => 'Interrompida',
                in_array($id, $partida->aguardandoProximaRodada(), true) => 'Próxima rodada',
                ! $partida->estaNaDisputa($id) => 'Fora da disputa',
                $partida->vezAtual() === $id => 'Vez atual',
                ! $resumo->paralelo && ($pendentes[1] ?? null) === $id => 'A seguir',
                in_array($id, $pendentes, true) => 'Aguardando',
                default => 'Vez encerrada',
            };
            $ordem[] = [
                'id' => $id,
                'nome' => PessoasDaMesa::nome($mesa, $id) ?? 'Participante indisponível',
                'primeiroNome' => (preg_split('/\s+/u', trim(PessoasDaMesa::nome($mesa, $id) ?? 'Participante')) ?: ['Participante'])[0],
                'iniciais' => self::iniciais(PessoasDaMesa::nome($mesa, $id) ?? 'Participante'),
                'local' => $mesa->participanteLocal($id) !== null,
                'atual' => $partida->vezAtual() === $id,
                'estado' => $estado,
                'resultado' => $tentativa?->situacao === SituacaoTentativa::Concluida
                    ? self::textoDoResultado($partida, $tentativa) : null,
            ];
        }

        $idDoPrazo = $participante ?? $partida->vezAtual();
        $tentativaDoPrazo = $idDoPrazo === null ? null : $partida->tentativaDe($partida->rodadaAtual(), $idDoPrazo, $partida->desempateAtual());
        $prazo = $tentativaDoPrazo?->situacao === SituacaoTentativa::EmAndamento
            ? $tentativaDoPrazo->prazoExpiraEm : $partida->prazoExpiraEm();
        if ($fase === 'fim' || $fase === 'entre-rodadas' || ($resumo->paralelo && ! in_array($fase, ['iniciar', 'jogar'], true))) {
            $prazo = null;
        }

        $motivoPausa = null;
        if ($partida->pausada()) {
            foreach (array_reverse($partida->eventos()) as $evento) {
                if ($evento->tipo === \App\Dominio\Partida\TipoEvento::PartidaPausada) {
                    $motivoPausa = $evento->origem === \App\Dominio\Partida\Origem::Admin
                        ? 'O administrador da mesa pausou a partida.'
                        : 'Todas as vezes passaram sem ninguém iniciar uma jogada. O sistema pausou a partida automaticamente.';
                    break;
                }
            }
        }

        $resultadosEntreRodadas = null;
        if ($fase === 'entre-rodadas') {
            $dados = $partida->resultadosEntreRodadas();
            if ($dados !== null) {
                $resultadosEntreRodadas = [
                    'rodada' => $dados['rodada'],
                    'alvo' => $dados['alvo'],
                    'resultados' => array_values(array_map(
                        static fn (array $r): array => [
                            'nome' => PessoasDaMesa::nome($mesa, $r['participante']) ?? 'Participante indisponível',
                            'tempoMedido' => $r['tempoMedido'],
                            'diferenca' => $r['diferenca'],
                            'posicao' => $r['posicao'],
                        ],
                        $dados['resultados'],
                    )),
                ];
            }
        }

        return new self($numero, $fase, json_encode([
            $numero, $fase, $partida->rodadaAtual(), $partida->desempateAtual(), $participante,
        ], JSON_THROW_ON_ERROR), $resumo, $ordem,
            $tentativaDoPrazo?->liberaEm?->format('Y-m-d\TH:i:s.vP'),
            $prazo?->format('Y-m-d\TH:i:s.vP'),
            app(Relogio::class)->agora()->format('Y-m-d\TH:i:s.vP'),
            $motivoPausa,
            $resultadosEntreRodadas,
        );
    }

    /** Resultado de uma tentativa concluída, no formato do jogo da partida. */
    public static function textoDoResultado(Partida $partida, Tentativa $tentativa): string
    {
        return $partida->configuracao()->criterio === Criterio::MaiorMelhor
            ? $tentativa->resultado.' pontos'
            : number_format($tentativa->resultado / 1000, 3, ',', '.').' s';
    }

    private static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: ['Participante'];

        return mb_strtoupper(mb_substr($partes[0], 0, 1).(count($partes) > 1 ? mb_substr($partes[array_key_last($partes)], 0, 1) : ''));
    }
}
