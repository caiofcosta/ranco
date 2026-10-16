<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Jogos\Labirinto\MapasDaPartida;
use App\Dominio\RegraViolada;
use App\Dominio\Relogio;
use DateTimeImmutable;

/**
 * Partida rodada após rodada. No modo sequencial, cada participante joga sua vez, na ordem.
 * No paralelo, a rodada abre para todos: cada um joga quando quiser, dentro do prazo, e a rodada
 * fecha quando não há pendentes. Tentativas iniciadas têm preparação e prazo próprio de conclusão.
 */
final class Partida
{
    private SituacaoPartida $situacao = SituacaoPartida::EmConfiguracao;

    /** @var list<string> Participantes na disputa, na ordem da partida. */
    private array $ativos;

    /** @var list<string> Admitidos durante a partida; entram na disputa na próxima rodada. */
    private array $aguardando = [];

    /** @var array<string, SituacaoParticipacao> */
    private array $participacoes = [];

    private int $rodada = 0;

    // Nulo nas partidas anteriores à integração: a rodada já aberta conserva o mapa legado.
    private ?MapasDaPartida $mapasLabirinto = null;

    /** @var array<int, int> Alvo em milissegundos por rodada do Tempo Certo. */
    private array $temposAlvo = [];

    /**
     * Nível de desempate herdado de partidas antigas. Partida nova permanece em 0:
     * o empate no 1º não abre mais tentativa extra.
     */
    private int $desempate = 0;

    /**
     * Quem ainda não concluiu a rodada. No sequencial, o primeiro está na vez; no paralelo,
     * todos podem jogar, e quem já iniciou continua aqui até concluir.
     *
     * @var list<string>
     */
    private array $fila = [];

    /** @var array<int, list<string>> */
    private array $participantesDaRodada = [];

    /** @var list<string> */
    private array $disputantesDoDesempate = [];

    /** @var list<list<string>> Grupos definidos abaixo do 1º durante os desempates, do melhor para o pior. */
    private array $gruposAbaixoDoPrimeiro = [];

    /** @var list<list<string>> Grupos abaixo do 1º definidos na fase normal da rodada. */
    private array $gruposDaFaseNormal = [];

    /** Início da contagem do prazo da vez atual; nulo enquanto o prazo não corre. */
    private ?DateTimeImmutable $prazoContandoDesde = null;

    private bool $pausada = false;

    private bool $encerramentoSolicitado = false;

    private bool $entreRodadas = false;

    private bool $houveRemocao = false;

    private ?MotivoEncerramento $motivoEncerramento = null;

    /** @var array<int, array<int, array<string, Tentativa>>> Rodada → nível de desempate → participante. */
    private array $tentativas = [];

    /** @var array<int, array<string, int>> */
    private array $posicoes = [];

    /** @var array<int, array<string, int>> */
    private array $pontos = [];

    /** @var list<Evento> */
    private array $eventos = [];

    /** Nulo em partida gravada antes deste campo existir. */
    private ?DateTimeImmutable $criadaEm = null;

    private function __construct(
        private ConfiguracaoPartida $configuracao,
        private readonly string $admin,
        private readonly Relogio $relogio,
    ) {
        $this->ativos = [$admin];
        $this->participacoes[$admin] = SituacaoParticipacao::Ativa;
    }

    public static function criar(ConfiguracaoPartida $configuracao, string $admin, Relogio $relogio): self
    {
        $partida = new self($configuracao, $admin, $relogio);
        $partida->criadaEm = $relogio->agora();

        return $partida;
    }

    public function temMapasLabirinto(): bool
    {
        return $this->mapasLabirinto !== null;
    }

    /** @param list<array{id: int, mapa_id: int, nome: string, definicao: array{formato: int, linhas: list<string>, tamanho_celula: int, raio_bolinha: int, velocidade: int}}> $versoes */
    public function prepararMapasLabirinto(array $versoes, bool $incluirRodadaAtual = false): void
    {
        if ($this->mapasLabirinto === null && $this->configuracao->jogo === 'labirinto') {
            $this->mapasLabirinto = new MapasDaPartida($versoes);
            if ($incluirRodadaAtual && $this->rodada > 0) {
                $this->mapasLabirinto->sortear($this->rodada);
            }
        }
    }

    /** @return array{id: int, mapa_id: int, nome: string, definicao: array{formato: int, linhas: list<string>, tamanho_celula: int, raio_bolinha: int, velocidade: int}}|null */
    public function mapaLabirinto(?int $rodada = null): ?array
    {
        return $this->mapasLabirinto?->daRodada($rodada ?? $this->rodada);
    }

    public function tempoAlvoDaRodada(?int $rodada = null): ?int
    {
        return $this->temposAlvo[$rodada ?? $this->rodada] ?? null;
    }

    // Configuração

    /** @param list<string> $locais Participantes locais ativos na mesa. */
    public function reconfigurar(ConfiguracaoPartida $configuracao, string $por, array $locais = []): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmConfiguracao);
        $ativos = $this->ativos;
        if ($configuracao->modo === ModoExecucao::Paralelo) {
            $ativos = array_values(array_diff($ativos, $locais));
        } elseif ($this->configuracao->modo === ModoExecucao::Paralelo) {
            $ativos = array_values(array_unique([...$ativos, ...$locais]));
        }
        if (count($ativos) > $configuracao->maximoJogadores) {
            throw new RegraViolada('A configuração não comporta os participantes da partida.');
        }
        foreach (array_diff($this->ativos, $ativos) as $id) {
            unset($this->participacoes[$id]);
        }
        foreach ($ativos as $id) {
            $this->participacoes[$id] = SituacaoParticipacao::Ativa;
        }
        $this->ativos = $ativos;
        $this->configuracao = $configuracao;
    }

    public function adicionarParticipante(string $participante): void
    {
        $this->exigirSituacao(SituacaoPartida::EmConfiguracao);
        if ($this->estaNaDisputa($participante)) {
            throw new RegraViolada("{$participante} já participa da partida.");
        }
        $this->exigirVagaPara($participante);

        $this->ativos[] = $participante;
        $this->participacoes[$participante] = SituacaoParticipacao::Ativa;
    }

    /** @param list<string> $novaOrdem */
    public function reordenar(array $novaOrdem, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmConfiguracao);

        $atual = $this->ativos;
        $nova = $novaOrdem;
        sort($atual);
        sort($nova);
        if ($atual !== $nova) {
            throw new RegraViolada('A nova ordem precisa conter exatamente os participantes da partida.');
        }

        $this->ativos = array_values($novaOrdem);
    }

    /** Em configuração e com o mínimo de jogadores do jogo. */
    public function podeIniciar(): bool
    {
        return $this->situacao === SituacaoPartida::EmConfiguracao
            && count($this->ativos) >= $this->configuracao->minimoJogadores;
    }

    public function iniciar(string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmConfiguracao);
        if (! $this->podeIniciar()) {
            throw new RegraViolada('A partida não atingiu o mínimo de jogadores.');
        }

        $agora = $this->relogio->agora();
        $this->situacao = SituacaoPartida::EmAndamento;
        $this->registrar(TipoEvento::PartidaIniciada, Origem::Admin, $agora);
        $this->abrirRodada(1, $agora);
    }

    // Tentativas

    public function iniciarTentativa(string $participante): void
    {
        $this->exigirSituacao(SituacaoPartida::EmAndamento);
        if ($this->pausada) {
            throw new RegraViolada('A partida está pausada.');
        }
        if ($this->encerramentoSolicitado) {
            throw new RegraViolada('A partida está sendo encerrada.');
        }
        if ($this->paralelo()) {
            if (! in_array($participante, $this->fila, true)) {
                throw new RegraViolada("{$participante} não tem jogada pendente nesta rodada.");
            }
            if ($this->tentativaDe($this->rodada, $participante, $this->desempate) !== null) {
                throw new RegraViolada("{$participante} já iniciou a jogada desta rodada.");
            }
        } elseif ($this->vezAtual() !== $participante) {
            throw new RegraViolada("Não é a vez de {$participante}.");
        }

        $this->tentativas[$this->rodada][$this->desempate][$participante] = new Tentativa($this->relogio->agora(), $this->configuracao->prazoVezSegundos);
        // No paralelo, o prazo da rodada continua correndo para quem ainda não começou.
        if (! $this->paralelo()) {
            $this->prazoContandoDesde = null;
        }
        $this->registrar(TipoEvento::TentativaIniciada, Origem::Jogador, $this->relogio->agora(), $participante);
    }

    public function temTentativaEmCurso(string $participante): bool
    {
        return $this->situacao === SituacaoPartida::EmAndamento && $this->tentativaEmCursoDe($participante) !== null;
    }

    public function registrarProgresso(string $participante, int $resultadoParcial): void
    {
        $this->tentativaEmAndamentoDe($participante)->registrarProgresso($resultadoParcial);
    }

    public function concluirTentativa(string $participante, int $resultado, ?int $tempoMedido = null): void
    {
        $mapa = $this->mapaLabirinto();
        if ($mapa !== null && $resultado < DesenhoLabirinto::tempoMinimo($mapa['definicao'])) {
            throw new RegraViolada('O tempo não é compatível com o mapa desta rodada.');
        }

        // O tempo medido na tela do jogador é o que vale: a latência de rede não pode recusar
        // uma vez legítima. A alteração casual do envio é barrada pelo lacre conferido no controller.
        $this->tentativaEmAndamentoDe($participante)->concluir($resultado, $tempoMedido);

        $agora = $this->relogio->agora();
        $this->registrar(TipoEvento::TentativaConcluida, Origem::Jogador, $agora, $participante);
        $this->depoisDaTentativa($participante, $agora);
    }

    public function interromperTentativa(string $participante): void
    {
        $this->tentativaEmAndamentoDe($participante)->interromper();

        $agora = $this->relogio->agora();
        $this->registrar(TipoEvento::TentativaInterrompida, Origem::Sistema, $agora, $participante);
        $this->depoisDaTentativa($participante, $agora);
    }

    /**
     * Grava a chave pública da transmissão na primeira aba que a envia. Outra aba da mesma vez
     * não troca a chave: devolve false, joga normalmente e sua transmissão fica sem validação.
     */
    public function registrarChaveTransmissao(string $participante, string $chave): bool
    {
        $tentativa = $this->tentativaEmCursoDe($participante) ?? throw new RegraViolada('O prazo desta vez acabou. Os acertos já confirmados foram mantidos.');
        $tentativa->chaveTransmissao ??= $chave;

        return $tentativa->chaveTransmissao === $chave;
    }

    /** Sequência: chave da transmissão da vez em andamento, ou null se ninguém está jogando. */
    public function chaveTransmissaoDaVez(): ?string
    {
        $participante = $this->participanteJogando();

        return $participante === null ? null : $this->tentativaDe($this->rodada, $participante, $this->desempate)?->chaveTransmissao;
    }

    // Ações do admin

    public function pularVez(string $participante, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmAndamento);
        if ($this->paralelo()) {
            throw new RegraViolada('Na partida em paralelo não há vez para pular: quem não jogar até o prazo perde a rodada.');
        }
        if ($this->vezAtual() !== $participante) {
            throw new RegraViolada("Não é a vez de {$participante}.");
        }
        if ($this->tentativaDe($this->rodada, $participante, $this->desempate) !== null) {
            throw new RegraViolada('Não é possível pular uma tentativa já iniciada.');
        }

        $agora = $this->relogio->agora();
        $this->registrar(TipoEvento::VezPulada, Origem::Admin, $agora, $participante);
        $this->avancarVez($agora);
    }

    public function pausar(string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmAndamento);
        if ($this->pausada) {
            throw new RegraViolada('A partida já está pausada.');
        }

        $this->pausarEm($this->relogio->agora(), Origem::Admin);
    }

    public function despausar(string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmAndamento);
        if (! $this->pausada) {
            throw new RegraViolada('A partida não está pausada.');
        }

        $agora = $this->relogio->agora();
        $this->pausada = false;
        $this->prazoContandoDesde = $this->temPrazoDeVez() && $this->haJogadaAguardandoInicio() && ! $this->encerramentoSolicitado ? $agora : null;
        $this->registrar(TipoEvento::PartidaRetomada, Origem::Admin, $agora);
    }

    /** Admite alguém na partida em andamento; a pessoa entra na disputa na próxima rodada, no fim da ordem. */
    public function admitir(string $participante, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmAndamento);
        if ($this->estaNaDisputa($participante)) {
            throw new RegraViolada("{$participante} já participa da partida.");
        }
        $this->exigirVagaPara($participante);

        $retorno = isset($this->participacoes[$participante]);
        $this->participacoes[$participante] = SituacaoParticipacao::Ativa;
        $this->aguardando[] = $participante;
        $this->registrar(
            $retorno ? TipoEvento::RetornouAPartida : TipoEvento::EntrouNaPartida,
            Origem::Admin,
            $this->relogio->agora(),
            $participante,
            $this->rodada + 1,
        );
    }

    public function removerParticipante(string $participante, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirNaoEncerrada();
        if ($participante === $this->admin) {
            throw new RegraViolada('O admin não pode remover a si mesmo.');
        }
        $this->exigirNaDisputa($participante);
        if ($this->tentativaEmCursoDe($participante) !== null) {
            throw new RegraViolada('Não é possível remover alguém durante uma tentativa em andamento.');
        }

        if ($this->situacao === SituacaoPartida::EmConfiguracao) {
            $this->retirar($participante);
            unset($this->participacoes[$participante]);

            return;
        }

        $agora = $this->relogio->agora();
        $this->houveRemocao = true;
        $this->participacoes[$participante] = SituacaoParticipacao::Removida;
        $this->registrar(TipoEvento::RemovidoDaPartida, Origem::Admin, $agora, $participante);
        $this->tirarDaDisputa($participante, $agora);
    }

    /** Encerra sem vencedor; se houver tentativa em andamento, espera ela terminar. */
    public function encerrar(string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirSituacao(SituacaoPartida::EmAndamento);
        if ($this->encerramentoSolicitado) {
            throw new RegraViolada('O encerramento da partida já foi solicitado.');
        }

        if ($this->tentativasEmCurso() !== []) {
            $this->encerramentoSolicitado = true;
            $this->prazoContandoDesde = null;

            return;
        }

        $this->finalizar(MotivoEncerramento::EncerradaPeloAdmin, Origem::Admin, $this->relogio->agora());
    }

    /** Chamado pela mesa quando o admin a abandona: encerra na hora, sem vencedor. */
    public function encerrarPorAbandonoDoAdmin(): void
    {
        $this->encerrarNaHora(MotivoEncerramento::AbandonoDoAdmin);
    }

    /** Chamado pela mesa quando o admin a encerra: encerra na hora, sem vencedor. */
    public function encerrarPorEncerramentoDaMesa(): void
    {
        $this->encerrarNaHora(MotivoEncerramento::EncerramentoDaMesa);
    }

    private function encerrarNaHora(MotivoEncerramento $motivo): void
    {
        if ($this->situacao === SituacaoPartida::Encerrada) {
            return;
        }

        $agora = $this->relogio->agora();
        foreach ($this->tentativasEmCurso() as $participante => $tentativa) {
            $tentativa->interromper();
            // A chave do array vira inteiro quando o identificador é numérico ('29' → 29).
            $this->registrar(TipoEvento::TentativaInterrompida, Origem::Sistema, $agora, (string) $participante);
        }

        $this->finalizar($motivo, Origem::Admin, $agora);
    }

    // Ações do participante

    public function abandonar(string $participante): void
    {
        $this->exigirNaoEncerrada();
        if ($participante === $this->admin) {
            throw new RegraViolada('O admin não abandona apenas a partida: ele encerra a partida ou abandona a mesa.');
        }
        $this->exigirNaDisputa($participante);

        if ($this->situacao === SituacaoPartida::EmConfiguracao) {
            $this->retirar($participante);
            unset($this->participacoes[$participante]);

            return;
        }

        $agora = $this->relogio->agora();
        $tentativa = $this->tentativaEmCursoDe($participante);
        if ($tentativa !== null) {
            $tentativa->interromper();
            $this->registrar(TipoEvento::TentativaInterrompida, Origem::Jogador, $agora, $participante);
        }

        $this->participacoes[$participante] = SituacaoParticipacao::Abandonada;
        $this->registrar(TipoEvento::AbandonouAPartida, Origem::Jogador, $agora, $participante);
        $this->tirarDaDisputa($participante, $agora);
    }

    // Prazo

    /**
     * Janela de graça para interromper uma tentativa já em curso cujo prazo acabou de expirar.
     * A Moedinha, por exemplo, termina no cliente exatamente no fim do prazo e envia a pontuação
     * logo em seguida; sem essa folga, qualquer leitura da mesa no mesmo instante (polling do
     * Livewire, outro jogador, broadcasting auth) processa o prazo antes do POST chegar e a
     * pontuação é perdida. Só vale para tentativa em curso — vez sem tentativa é pulada na hora.
     */
    private const GRACA_APOS_PRAZO_SEGUNDOS = 5;

    /** Pula, em cascata, as vezes (no paralelo, as rodadas) cujo prazo expirou até agora. */
    public function processarPrazos(): void
    {
        if (! $this->temPrazoDeVez()) {
            return;
        }

        $agora = $this->relogio->agora();
        $comGraca = $agora->modify('-'.self::GRACA_APOS_PRAZO_SEGUNDOS.' seconds');

        while ($this->situacao === SituacaoPartida::EmAndamento) {
            $expiraEm = $this->prazoExpiraEm();
            $expirando = null;
            foreach ($this->tentativasEmCurso() as $id => $tentativa) {
                if ($tentativa->prazoExpiraEm !== null && ($expiraEm === null || $tentativa->prazoExpiraEm < $expiraEm)) {
                    $expiraEm = $tentativa->prazoExpiraEm;
                    $expirando = (string) $id;
                }
            }
            if ($expiraEm === null) {
                return;
            }
            // Tentativa em curso: espera a janela de graça. Vez pulada (sem tentativa): processa na hora.
            $referencia = $expirando !== null ? $comGraca : $agora;
            if ($referencia < $expiraEm) {
                return;
            }
            if ($expirando !== null) {
                $this->tentativaEmAndamentoDe($expirando)->interromper();
                $this->registrar(TipoEvento::TentativaInterrompida, Origem::Sistema, $expiraEm, $expirando);
                $this->depoisDaTentativa($expirando, $expiraEm);

                continue;
            }
            if ($this->paralelo()) {
                $this->expirarRodadaParalela($expiraEm);

                continue;
            }
            $this->registrar(TipoEvento::VezPulada, Origem::Sistema, $expiraEm, $this->vezAtual());
            $this->avancarVez($expiraEm);
        }
    }

    // Consultas

    public function situacao(): SituacaoPartida
    {
        return $this->situacao;
    }

    /** @return list<string> */
    public function ordem(): array
    {
        return $this->ativos;
    }

    /** @return list<string> */
    public function aguardandoProximaRodada(): array
    {
        return $this->aguardando;
    }

    public function estaNaDisputa(string $participante): bool
    {
        return in_array($participante, $this->ativos, true) || in_array($participante, $this->aguardando, true);
    }

    public function situacaoDe(string $participante): ?SituacaoParticipacao
    {
        return $this->participacoes[$participante] ?? null;
    }

    public function rodadaAtual(): int
    {
        return $this->rodada;
    }

    public function desempateAtual(): int
    {
        return $this->desempate;
    }

    /** @return list<string> */
    public function participantesDaRodada(int $rodada): array
    {
        return $this->participantesDaRodada[$rodada] ?? [];
    }

    /** Só no sequencial; no paralelo não há vez, e cada pendente pode jogar. */
    public function vezAtual(): ?string
    {
        if ($this->paralelo()) {
            return null;
        }

        return $this->situacao === SituacaoPartida::EmAndamento ? ($this->fila[0] ?? null) : null;
    }

    /** Quem está efetivamente jogando a vez sequencial, inclusive durante uma pausa. */
    public function participanteJogando(): ?string
    {
        $vez = $this->vezAtual();

        return $vez !== null && $this->tentativaEmCursoDe($vez) !== null ? $vez : null;
    }

    public function modo(): ModoExecucao
    {
        return $this->configuracao->modo;
    }

    /**
     * Quem ainda não concluiu a rodada em andamento, na ordem da partida.
     *
     * @return list<string>
     */
    public function pendentesDaRodada(): array
    {
        return $this->situacao === SituacaoPartida::EmAndamento ? $this->fila : [];
    }

    /** No sequencial, se é a vez da pessoa; no paralelo, se ela ainda não concluiu a rodada. */
    public function podeJogarAgora(string $participante): bool
    {
        if ($this->situacao !== SituacaoPartida::EmAndamento) {
            return false;
        }

        return $this->paralelo()
            ? in_array($participante, $this->fila, true)
            : $this->vezAtual() === $participante;
    }

    public function pausada(): bool
    {
        return $this->pausada;
    }

    public function entreRodadas(): bool
    {
        return $this->entreRodadas;
    }

    /**
     * @return array{rodada: int, alvo: int, resultados: list<array{participante: string, tempoMedido: ?int, diferenca: int, posicao: int}>}|null
     */
    public function resultadosEntreRodadas(): ?array
    {
        if (! $this->entreRodadas) {
            return null;
        }

        $resultados = [];
        foreach ($this->tentativas[$this->rodada][0] ?? [] as $participante => $tentativa) {
            $resultados[] = [
                'participante' => (string) $participante,
                'tempoMedido' => $tentativa->tempoMedido,
                'diferenca' => $tentativa->resultado,
                'posicao' => $this->posicoes[$this->rodada][$participante] ?? 0,
            ];
        }
        usort($resultados, static fn (array $a, array $b): int => $a['posicao'] <=> $b['posicao']);

        return [
            'rodada' => $this->rodada,
            'alvo' => $this->temposAlvo[$this->rodada] ?? 0,
            'resultados' => $resultados,
        ];
    }

    public function iniciarProximaRodada(string $por): void
    {
        if (! $this->entreRodadas || $this->situacao !== SituacaoPartida::EmAndamento) {
            throw new RegraViolada('A partida não está aguardando o início da próxima rodada.');
        }
        if ($por !== $this->admin) {
            throw new RegraViolada('Somente o administrador pode iniciar a próxima rodada.');
        }

        $this->entreRodadas = false;
        $this->abrirRodada($this->rodada + 1, $this->relogio->agora());
    }

    public function tentativaDe(int $rodada, string $participante, int $desempate = 0): ?Tentativa
    {
        return $this->tentativas[$rodada][$desempate][$participante] ?? null;
    }

    public function posicaoNaRodada(int $rodada, string $participante): ?int
    {
        return $this->posicoes[$rodada][$participante] ?? null;
    }

    public function pontosNaRodada(int $rodada, string $participante): int
    {
        return $this->pontos[$rodada][$participante] ?? 0;
    }

    /**
     * Registra o resultado de uma rodada simultânea que não usa Tentativa.
     * Usado pelo Stop, cujo resultado nasce da revisão das respostas.
     *
     * @param  array<string, int>  $posicoes
     * @param  array<string, int>  $pontos
     */
    public function concluirRodadaSimultanea(array $posicoes, array $pontos, DateTimeImmutable $em): void
    {
        $this->exigirSituacao(SituacaoPartida::EmAndamento);
        if ($this->rodada < 1 || isset($this->pontos[$this->rodada])) {
            throw new RegraViolada('A rodada atual não pode receber outro resultado.');
        }

        $this->posicoes[$this->rodada] = $posicoes;
        $this->pontos[$this->rodada] = $pontos;
        $this->registrar(TipoEvento::RodadaConcluida, Origem::Sistema, $em);

        if ($this->verificarTermino($em)) {
            return;
        }

        $this->abrirRodada($this->rodada + 1, $em);
    }

    public function totalDe(string $participante): int
    {
        $total = 0;
        foreach ($this->pontos as $pontosDaRodada) {
            $total += $pontosDaRodada[$participante] ?? 0;
        }

        return $total;
    }

    public function motivoEncerramento(): ?MotivoEncerramento
    {
        return $this->motivoEncerramento;
    }

    public function criadaEm(): ?DateTimeImmutable
    {
        return $this->criadaEm;
    }

    /**
     * Primeiro de vencedores(), na ordem da classificação.
     * Null somente quando não há 1º lugar. Empate no 1º não devolve null.
     */
    public function vencedor(): ?string
    {
        return $this->vencedores()[0] ?? null;
    }

    /**
     * Quem ficou em 1º na classificação, inclusive quem saiu da partida.
     *
     * @return list<string>
     */
    public function vencedores(): array
    {
        $ids = [];
        foreach ($this->classificacao() as $linha) {
            if ($linha['colocacao'] !== 1) {
                break;
            }
            $ids[] = $linha['pessoa'];
        }

        return $ids;
    }

    /**
     * Colocação final, densa, de uma partida encerrada com vencedor. Empate fica na mesma colocação.
     * Quem saiu entra pelo total. No W.O. quem ficou é o único 1º.
     *
     * @return list<array{pessoa: string, colocacao: int, empate: bool}>
     */
    public function classificacao(): array
    {
        if ($this->motivoEncerramento !== MotivoEncerramento::Normal
            && $this->motivoEncerramento !== MotivoEncerramento::Desistencia) {
            return [];
        }

        $pontos = [];
        foreach (array_keys($this->participacoes) as $pessoa) {
            $pessoa = (string) $pessoa;
            $pontos[$pessoa] = $this->totalDe($pessoa);
        }

        if ($this->motivoEncerramento === MotivoEncerramento::Desistencia) {
            $unico = $this->admin;
            unset($pontos[$unico]);
            $grupos = $pontos === [] ? [] : Classificador::agrupar($pontos, Criterio::MaiorMelhor);
            array_unshift($grupos, [$unico]);
        } else {
            $grupos = Classificador::agrupar($pontos, Criterio::MaiorMelhor);
        }

        $linhas = [];
        foreach ($grupos as $indice => $grupo) {
            $empate = count($grupo) > 1;
            foreach ($grupo as $pessoa) {
                $linhas[] = ['pessoa' => $pessoa, 'colocacao' => $indice + 1, 'empate' => $empate];
            }
        }

        return $linhas;
    }

    /** @return list<Evento> */
    public function eventos(): array
    {
        return $this->eventos;
    }

    public function admin(): string
    {
        return $this->admin;
    }

    public function configuracao(): ConfiguracaoPartida
    {
        return $this->configuracao;
    }

    public function prazoExpiraEm(): ?DateTimeImmutable
    {
        return $this->prazoContandoDesde?->modify("+{$this->configuracao->prazoVezSegundos} seconds");
    }

    public function encerramentoSolicitado(): bool
    {
        return $this->encerramentoSolicitado;
    }

    /** @return list<array{participante: string, pontos: int, situacao: SituacaoParticipacao}> */
    public function placar(): array
    {
        $placar = [];
        foreach ($this->participacoes as $participante => $situacao) {
            $participante = (string) $participante;
            $placar[] = ['participante' => $participante, 'pontos' => $this->totalDe($participante), 'situacao' => $situacao];
        }

        return $placar;
    }

    // Persistência: o relógio não é gravado; quem carrega a partida religa o relógio.

    public function religar(Relogio $relogio): void
    {
        $this->relogio = $relogio;
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        $dados = get_object_vars($this);
        unset($dados['relogio']);

        return $dados;
    }

    /** @param array<string, mixed> $dados */
    public function __unserialize(array $dados): void
    {
        unset($dados['vencedor'], $dados['vencedores']);
        $dados['criadaEm'] ??= null;
        $dados['temposAlvo'] ??= [];
        $dados['entreRodadas'] ??= false;

        foreach ($dados as $propriedade => $valor) {
            $this->{$propriedade} = $valor;
        }
    }

    // Fluxo interno

    private function abrirRodada(int $numero, DateTimeImmutable $em): void
    {
        $this->mapasLabirinto?->sortear($numero);
        $this->rodada = $numero;
        $this->desempate = 0;
        $this->ativos = [...$this->ativos, ...$this->aguardando];
        $this->aguardando = [];
        $this->participantesDaRodada[$numero] = $this->ativos;
        $this->fila = $this->ativos;
        $this->abrirVez($em);
    }

    private function abrirVez(DateTimeImmutable $em): void
    {
        $this->prazoContandoDesde = $this->pausada || $this->encerramentoSolicitado || ! $this->temPrazoDeVez() ? null : $em;
    }

    private function depoisDaTentativa(string $participante, DateTimeImmutable $em): void
    {
        if (! $this->paralelo()) {
            if ($this->encerramentoSolicitado) {
                $this->finalizar(MotivoEncerramento::EncerradaPeloAdmin, Origem::Admin, $em);

                return;
            }

            $this->avancarVez($em);

            return;
        }

        $this->tirarDaFila($participante);
        if ($this->encerramentoSolicitado) {
            // O encerramento espera todas as tentativas já iniciadas terminarem.
            if ($this->tentativasEmCurso() === []) {
                $this->finalizar(MotivoEncerramento::EncerradaPeloAdmin, Origem::Admin, $em);
            }

            return;
        }

        if ($this->fila === []) {
            $this->concluirFase($em);
        }
    }

    /** Prazo de início paralelo vencido: quem não começou perde a rodada; iniciadas seguem seu prazo próprio. */
    private function expirarRodadaParalela(DateTimeImmutable $em): void
    {
        $this->prazoContandoDesde = null;
        foreach ($this->fila as $participante) {
            if ($this->tentativaDe($this->rodada, $participante, $this->desempate) === null) {
                $this->registrar(TipoEvento::VezPulada, Origem::Sistema, $em, $participante);
                $this->tirarDaFila($participante);
            }
        }

        if ($this->fila === []) {
            $this->concluirFase($em);
        }
    }

    /** O Labirinto usa a vez genérica: cada jogada tem seu prazo e a pausa automática. */
    private function temPrazoDeVez(): bool
    {
        return true;
    }

    private function paralelo(): bool
    {
        return $this->configuracao->modo === ModoExecucao::Paralelo;
    }

    private function tirarDaFila(string $participante): void
    {
        $this->fila = array_values(array_filter(
            $this->fila,
            static fn (string $item): bool => $item !== $participante,
        ));
    }

    /** Se o prazo deve correr: no sequencial, a vez ainda não começou; no paralelo, alguém ainda não começou. */
    private function haJogadaAguardandoInicio(): bool
    {
        if (! $this->paralelo()) {
            return $this->fila !== [] && $this->tentativasEmCurso() === [];
        }

        foreach ($this->fila as $participante) {
            if ($this->tentativaDe($this->rodada, $participante, $this->desempate) === null) {
                return true;
            }
        }

        return false;
    }

    private function avancarVez(DateTimeImmutable $em): void
    {
        array_shift($this->fila);
        $this->seguirFila($em);
    }

    private function seguirFila(DateTimeImmutable $em): void
    {
        if ($this->desempate > 0) {
            $this->fila = [];
            $this->concluirFase($em);

            return;
        }

        if ($this->fila !== []) {
            $this->abrirVez($em);

            return;
        }

        $this->concluirFase($em);
    }

    /** Fecha a rodada. Empate no 1º permanece na mesma colocação, sem tentativa extra. */
    private function concluirFase(DateTimeImmutable $em): void
    {
        $this->desempate = 0;
        $this->disputantesDoDesempate = [];
        $grupos = $this->agrupar($this->participantesDaRodada[$this->rodada] ?? []);
        $this->gruposAbaixoDoPrimeiro = [];
        $this->gruposDaFaseNormal = array_slice($grupos, 1);
        $this->concluirRodada($grupos[0] ?? [], $em);
    }

    /** @param list<string> $primeiros */
    private function concluirRodada(array $primeiros, DateTimeImmutable $em): void
    {
        $grupos = array_values(array_filter(
            [$primeiros, ...$this->gruposAbaixoDoPrimeiro, ...$this->gruposDaFaseNormal],
            static fn (array $grupo): bool => $grupo !== [],
        ));

        $this->posicoes[$this->rodada] = [];
        $this->pontos[$this->rodada] = [];
        foreach ($grupos as $indice => $grupo) {
            foreach ($grupo as $participante) {
                $this->posicoes[$this->rodada][$participante] = $indice + 1;
                $this->pontos[$this->rodada][$participante] = $this->configuracao->pontosPorPosicao[$indice] ?? 0;
            }
        }
        $this->registrar(TipoEvento::RodadaConcluida, Origem::Sistema, $em);

        if ($this->verificarTermino($em)) {
            return;
        }

        $ninguemJogou = ($this->tentativas[$this->rodada] ?? []) === [];
        if ($this->configuracao->intervaloEntreRodadas) {
            $this->entreRodadas = true;
            if ($ninguemJogou && ! $this->pausada && $this->temPrazoDeVez()) {
                $this->pausarEm($em, Origem::Sistema);
            }

            return;
        }
        $this->abrirRodada($this->rodada + 1, $em);
        if ($ninguemJogou && ! $this->pausada && $this->temPrazoDeVez()) {
            $this->pausarEm($em, Origem::Sistema);
        }
    }

    /** Meta de pontos e limite de rodadas, avaliados ao fim da rodada. Empate no total divide o 1º. */
    private function verificarTermino(DateTimeImmutable $em): bool
    {
        $meta = $this->configuracao->metaPontos;
        $limite = $this->configuracao->limiteRodadas;

        $totais = [];
        foreach ($this->ativos as $participante) {
            $totais[$participante] = $this->totalDe($participante);
        }

        $atingiuMeta = $meta !== null && max($totais) >= $meta;
        $atingiuLimite = $limite !== null && $this->rodada >= $limite;
        if (! $atingiuMeta && ! $atingiuLimite) {
            return false;
        }

        $this->finalizar(MotivoEncerramento::Normal, Origem::Sistema, $em);

        return true;
    }

    private function finalizar(MotivoEncerramento $motivo, Origem $origem, DateTimeImmutable $em): void
    {
        $this->situacao = SituacaoPartida::Encerrada;
        $this->motivoEncerramento = $motivo;
        $this->fila = [];
        $this->prazoContandoDesde = null;
        $this->pausada = false;
        $this->encerramentoSolicitado = false;

        foreach ([...$this->ativos, ...$this->aguardando] as $participante) {
            $this->participacoes[$participante] = SituacaoParticipacao::Concluida;
        }

        $this->registrar(TipoEvento::PartidaEncerrada, $origem, $em);
    }

    private function tirarDaDisputa(string $participante, DateTimeImmutable $em): void
    {
        $eraAVez = ($this->fila[0] ?? null) === $participante;
        $this->retirar($participante);

        if ([...$this->ativos, ...$this->aguardando] === [$this->admin]) {
            $this->houveRemocao
                ? $this->finalizar(MotivoEncerramento::Remocoes, Origem::Sistema, $em)
                : $this->finalizar(MotivoEncerramento::Desistencia, Origem::Sistema, $em);

            return;
        }

        if ($this->encerramentoSolicitado && $this->tentativasEmCurso() === []) {
            $this->finalizar(MotivoEncerramento::EncerradaPeloAdmin, Origem::Admin, $em);

            return;
        }

        if ($this->paralelo()) {
            if (! $this->encerramentoSolicitado && $this->fila === []) {
                $this->concluirFase($em);
            }

            return;
        }

        if ($eraAVez) {
            $this->seguirFila($em);
        }
    }

    private function retirar(string $participante): void
    {
        $semEle = static fn (array $lista): array => array_values(array_filter(
            $lista,
            static fn (string $item): bool => $item !== $participante,
        ));

        $this->ativos = $semEle($this->ativos);
        $this->aguardando = $semEle($this->aguardando);
        $this->fila = $semEle($this->fila);
    }

    private function pausarEm(DateTimeImmutable $em, Origem $origem): void
    {
        $this->pausada = true;
        $this->prazoContandoDesde = null;
        $this->registrar(TipoEvento::PartidaPausada, $origem, $em);
    }

    /**
     * @param  list<string>  $participantes
     * @return list<list<string>>
     */
    private function agrupar(array $participantes): array
    {
        $resultados = [];
        foreach ($participantes as $participante) {
            $resultado = $this->resultadoValido($this->tentativaDe($this->rodada, $participante, $this->desempate));
            if ($resultado !== null) {
                $resultados[$participante] = $resultado;
            }
        }

        return Classificador::agrupar($resultados, $this->configuracao->criterio);
    }

    /**
     * Tentativa concluída sempre vale. Interrompida vale pelo progresso só em jogos de pontos:
     * em jogos de tempo, quem não terminou não tem tempo comparável.
     */
    private function resultadoValido(?Tentativa $tentativa): ?int
    {
        return match (true) {
            $tentativa === null => null,
            $tentativa->situacao === SituacaoTentativa::Concluida => $tentativa->resultado,
            $tentativa->situacao === SituacaoTentativa::Interrompida
                && $this->configuracao->criterio === Criterio::MaiorMelhor => $tentativa->resultado,
            default => null,
        };
    }

    /**
     * No sequencial, no máximo a de quem está na vez; no paralelo, todas as iniciadas e não concluídas.
     *
     * @return array<string, Tentativa>
     */
    private function tentativasEmCurso(): array
    {
        $candidatos = $this->paralelo() ? $this->fila : array_slice($this->fila, 0, 1);
        $emCurso = [];
        foreach ($candidatos as $participante) {
            $tentativa = $this->tentativaEmCursoDe($participante);
            if ($tentativa !== null) {
                $emCurso[$participante] = $tentativa;
            }
        }

        return $emCurso;
    }

    private function tentativaEmCursoDe(string $participante): ?Tentativa
    {
        $naFila = $this->paralelo()
            ? in_array($participante, $this->fila, true)
            : ($this->fila[0] ?? null) === $participante;
        if (! $naFila) {
            return null;
        }

        $tentativa = $this->tentativaDe($this->rodada, $participante, $this->desempate);

        return $tentativa?->situacao === SituacaoTentativa::EmAndamento ? $tentativa : null;
    }

    private function tentativaEmAndamentoDe(string $participante): Tentativa
    {
        $tentativa = $this->tentativaEmCursoDe($participante);
        if ($tentativa === null) {
            throw new RegraViolada("{$participante} não tem tentativa em andamento.");
        }

        return $tentativa;
    }

    private function exigirVagaPara(string $participante): void
    {
        if (count($this->ativos) + count($this->aguardando) >= $this->configuracao->maximoJogadores) {
            throw new RegraViolada("A partida atingiu o máximo de jogadores; {$participante} não pode entrar.");
        }
    }

    private function exigirNaDisputa(string $participante): void
    {
        if (! $this->estaNaDisputa($participante)) {
            throw new RegraViolada("{$participante} não está na disputa.");
        }
    }

    private function exigirAdmin(string $quem): void
    {
        if ($quem !== $this->admin) {
            throw new RegraViolada('Somente o admin pode realizar esta ação.');
        }
    }

    private function exigirSituacao(SituacaoPartida $esperada): void
    {
        if ($this->situacao !== $esperada) {
            throw new RegraViolada("Ação não permitida com a partida em {$this->situacao->value}.");
        }
    }

    private function exigirNaoEncerrada(): void
    {
        if ($this->situacao === SituacaoPartida::Encerrada) {
            throw new RegraViolada('A partida já foi encerrada.');
        }
    }

    private function registrar(
        TipoEvento $tipo,
        Origem $origem,
        DateTimeImmutable $em,
        ?string $participante = null,
        ?int $rodada = null,
    ): void {
        $this->eventos[] = new Evento($tipo, $origem, $em, $participante, $rodada ?? $this->rodada);
    }
}
