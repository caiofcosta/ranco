<?php

declare(strict_types=1);

namespace App\Dominio\Mesa;

use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\RegraViolada;
use App\Dominio\Relogio;
use DateTimeImmutable;

/**
 * Agrupa pessoas sob um admin. Pode ter várias partidas no histórico, mas só uma ativa por vez.
 */
final class Mesa
{
    public const TAMANHO_MAXIMO_DO_NOME = 40;

    private SituacaoMesa $situacao = SituacaoMesa::Aberta;

    private string $nome = '';

    /** Nulo preserva a preparação das mesas anteriores à escolha explícita de lugares. */
    private ?int $lugares = null;

    public function quantidadeLugares(): int
    {
        return $this->lugares ?? 8;
    }

    public function configurarLugares(int $quantidade, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();
        if ($quantidade < 2 || $quantidade > 8) {
            throw new RegraViolada('Escolha entre 2 e 8 lugares.');
        }
        if ($this->partidaAtiva()?->situacao() === SituacaoPartida::EmAndamento) {
            throw new RegraViolada('Altere os lugares depois de encerrar a partida.');
        }
        if ($quantidade < count($this->membrosAtivos())) {
            throw new RegraViolada('A quantidade não pode ser menor que o número de pessoas na mesa.');
        }
        $configuracao = $this->partidaAtiva()?->configuracao();
        if ($configuracao !== null && ($quantidade < $configuracao->minimoJogadores || $quantidade > $configuracao->maximoJogadores)) {
            throw new RegraViolada('Essa quantidade de lugares não é permitida pelo jogo.');
        }
        $this->lugares = $quantidade;
    }

    public function podeIniciarPartida(): bool
    {
        return $this->partidaAtiva()?->situacao() === SituacaoPartida::EmConfiguracao
            && $this->pendenciasParaIniciar() === [];
    }

    /**
     * O que falta para o admin iniciar a próxima partida. Vazio quando pode começar ou quando a
     * partida já está em andamento. Lugares vagos não impedem o início, e quem está sentado conta
     * para o mínimo mesmo sem ter entrado na partida ainda.
     *
     * @return list<PendenciaParaIniciar>
     */
    public function pendenciasParaIniciar(): array
    {
        $partida = $this->partidaAtiva();
        if ($partida === null) {
            return [PendenciaParaIniciar::EscolherJogo];
        }
        if ($partida->situacao() !== SituacaoPartida::EmConfiguracao) {
            return [];
        }

        $pendencias = [];
        if (count($partida->ordem()) + count($this->sentadosForaDaPartida($partida)) < $partida->configuracao()->minimoJogadores) {
            $pendencias[] = PendenciaParaIniciar::MinimoDeJogadores;
        }

        return $pendencias;
    }

    public function iniciarPartida(string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();
        $partida = $this->exigirPartidaAtiva();
        foreach ($this->sentadosForaDaPartida($partida) as $sentado) {
            $partida->adicionarParticipante($sentado);
        }
        $partida->iniciar($por);
    }

    /**
     * Sentados que ainda não estão na partida em configuração: entram nela ao começar e contam
     * para o início. Participantes locais ficam de fora quando o jogo não os aceita.
     *
     * @return list<string>
     */
    private function sentadosForaDaPartida(Partida $partida): array
    {
        if ($partida->situacao() !== SituacaoPartida::EmConfiguracao) {
            return [];
        }

        $configuracao = $partida->configuracao();
        $semLocais = $configuracao->modo === ModoExecucao::Paralelo;
        $sentados = [];
        foreach ($this->membrosAtivos() as $membro) {
            if ($partida->estaNaDisputa($membro) || ($semLocais && isset($this->participantesLocais[$membro]))) {
                continue;
            }
            $sentados[] = $membro;
        }

        return $sentados;
    }

    private function exigirLugarLivre(): void
    {
        if (count($this->membrosAtivos()) >= $this->quantidadeLugares()) {
            throw new RegraViolada('Todos os lugares da mesa estão ocupados.');
        }
    }

    private function validarLugaresDoJogo(ConfiguracaoPartida $configuracao): void
    {
        if ($this->lugares !== null && ($this->lugares < $configuracao->minimoJogadores || $this->lugares > $configuracao->maximoJogadores)) {
            throw new RegraViolada('Ajuste os lugares da mesa para a quantidade permitida pelo jogo.');
        }
    }

    /** @var array<string, SituacaoNaMesa> Na ordem de entrada. */
    private array $membros = [];

    /** @var array<string, ParticipanteLocal> */
    private array $participantesLocais = [];

    /** @var list<Partida> */
    private array $partidas = [];

    /** Nulo em mesa gravada antes deste campo existir. */
    private ?DateTimeImmutable $criadaEm = null;

    private function __construct(
        public readonly string $id,
        private readonly string $admin,
        private readonly Relogio $relogio,
    ) {
        $this->membros[$admin] = SituacaoNaMesa::Ativa;
    }

    public static function criar(string $id, string $admin, Relogio $relogio, string $nome = ''): self
    {
        $mesa = new self($id, $admin, $relogio);
        $mesa->nome = $mesa->normalizarNome($nome);
        $mesa->criadaEm = $relogio->agora();

        return $mesa;
    }

    // Membros

    /** Entrada na mesa (por convite aceito). Quem volta depois de sair entra no fim da ordem de entrada. */
    public function adicionarMembro(string $usuario): void
    {
        $this->exigirAberta();
        if ($this->membroAtivo($usuario)) {
            throw new RegraViolada("{$usuario} já está na mesa.");
        }

        $this->exigirLugarLivre();
        unset($this->membros[$usuario]);
        $this->membros[$usuario] = SituacaoNaMesa::Ativa;
    }

    public function cadastrarParticipanteLocal(string $nome, string $email, string $por): string
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();

        $this->exigirLugarLivre();

        $id = "{$this->id}:local:" . (count($this->participantesLocais) + 1);
        $this->participantesLocais[$id] = new ParticipanteLocal($id, $nome, $email);
        $this->membros[$id] = SituacaoNaMesa::Ativa;

        return $id;
    }

    /** Admite um membro na partida ativa: na configuração entra direto; em andamento, na próxima rodada. */
    public function admitirNaPartida(string $usuario, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirMembroAtivo($usuario);
        $partida = $this->exigirPartidaAtiva();
        if ($partida->modo() === ModoExecucao::Paralelo && isset($this->participantesLocais[$usuario])) {
            throw new RegraViolada('Partida em paralelo não aceita participante local.');
        }

        if ($partida->situacao() === SituacaoPartida::EmConfiguracao) {
            $partida->adicionarParticipante($usuario);

            return;
        }

        $partida->admitir($usuario, $por);
    }

    public function removerDaPartida(string $usuario, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirPartidaAtiva()->removerParticipante($usuario, $por);
    }

    /** Remover da mesa também remove da partida ativa; os resultados já obtidos permanecem. */
    public function removerDaMesa(string $usuario, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();
        if ($usuario === $this->admin) {
            throw new RegraViolada('O admin não pode remover a si mesmo.');
        }
        $this->exigirMembroAtivo($usuario);

        $partida = $this->partidaAtiva();
        if ($partida !== null && $partida->estaNaDisputa($usuario)) {
            $partida->removerParticipante($usuario, $por);
        }

        $this->membros[$usuario] = SituacaoNaMesa::Removida;
    }

    public function abandonarPartida(string $usuario): void
    {
        $this->exigirMembroAtivo($usuario);
        $this->exigirPartidaAtiva()->abandonar($usuario);
    }

    /** O admin que abandona encerra a mesa e a partida ativa, sem vencedor. */
    public function abandonar(string $usuario): void
    {
        $this->exigirAberta();
        $this->exigirMembroAtivo($usuario);

        if ($usuario === $this->admin) {
            $this->partidaAtiva()?->encerrarPorAbandonoDoAdmin();
            $this->membros[$usuario] = SituacaoNaMesa::Abandonada;
            $this->situacao = SituacaoMesa::Encerrada;

            return;
        }

        $partida = $this->partidaAtiva();
        if ($partida !== null && $partida->estaNaDisputa($usuario)) {
            $partida->abandonar($usuario);
        }

        $this->membros[$usuario] = SituacaoNaMesa::Abandonada;
    }

    /**
     * O admin encerra a mesa: a partida ativa termina sem vencedor, o nome permanece e todos
     * ficam livres para outra mesa. Não é abandono.
     */
    public function encerrar(string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();
        $this->partidaAtiva()?->encerrarPorEncerramentoDaMesa();
        $this->situacao = SituacaoMesa::Encerrada;
    }

    public function renomear(string $nome, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();
        $this->nome = $this->normalizarNome($nome);
    }

    // Partidas

    /**
     * A ordem inicial nasce da ordem de entrada na mesa. Na partida em paralelo, os participantes
     * locais ficam de fora (dividem o aparelho do admin), mas continuam na mesa.
     */
    public function novaPartida(ConfiguracaoPartida $configuracao, string $por): Partida
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();
        if ($this->partidaAtiva() !== null) {
            throw new RegraViolada('A mesa já tem uma partida ativa.');
        }

        $this->validarLugaresDoJogo($configuracao);
        $partida = Partida::criar($configuracao, $this->admin, $this->relogio);
        foreach ($this->sentadosForaDaPartida($partida) as $sentado) {
            $partida->adicionarParticipante($sentado);
        }
        $this->partidas[] = $partida;

        return $partida;
    }

    public function reconfigurarPartida(ConfiguracaoPartida $configuracao, string $por): void
    {
        $this->exigirAdmin($por);
        $this->exigirAberta();
        $this->validarLugaresDoJogo($configuracao);
        $locais = array_values(array_filter($this->membrosAtivos(), fn (string $id): bool => isset($this->participantesLocais[$id])));
        $this->exigirPartidaAtiva()->reconfigurar($configuracao, $por, $locais);
    }

    public function encerrarPartida(string $por): void
    {
        $this->exigirPartidaAtiva()->encerrar($por);
    }

    // Consultas

    /**
     * Por quem a conta joga agora (pausa à parte). Paralelo: ela mesma, se ainda não concluiu a
     * rodada. Sequencial: a vez atual, se for dela ou de um participante local e ela for a admin.
     */
    public function porQuemJoga(string $usuario): ?string
    {
        $partida = $this->partidaAtiva();
        if ($partida === null || $partida->situacao() !== SituacaoPartida::EmAndamento || !$this->membroAtivo($usuario)) {
            return null;
        }
        if ($partida->modo() === ModoExecucao::Paralelo) {
            return $partida->podeJogarAgora($usuario) ? $usuario : null;
        }

        $vez = $partida->vezAtual();
        if ($vez === null) {
            return null;
        }

        return $this->podeJogarPor($usuario, $vez) ? $vez : null;
    }

    /** A conta joga por ela mesma; a admin joga também pelos participantes locais. */
    public function podeJogarPor(string $usuario, string $participante): bool
    {
        return $participante === $usuario
            || ($this->admin() === $usuario && $this->participanteLocal($participante) !== null);
    }

    public function admin(): string
    {
        return $this->admin;
    }

    /** Vazio: a tela mostra "Mesa de {nome do admin}". */
    public function nome(): string
    {
        return $this->nome;
    }

    public function situacao(): SituacaoMesa
    {
        return $this->situacao;
    }

    public function criadaEm(): ?DateTimeImmutable
    {
        return $this->criadaEm;
    }

    public function partidaAtiva(): ?Partida
    {
        $ultima = $this->partidas === [] ? null : $this->partidas[array_key_last($this->partidas)];

        return $ultima?->situacao() === SituacaoPartida::Encerrada ? null : $ultima;
    }

    /** @return list<Partida> */
    public function partidas(): array
    {
        return $this->partidas;
    }

    public function membroAtivo(string $usuario): bool
    {
        return $this->situacao === SituacaoMesa::Aberta
            && ($this->membros[$usuario] ?? null) === SituacaoNaMesa::Ativa;
    }

    public function situacaoDe(string $usuario): ?SituacaoNaMesa
    {
        return $this->membros[$usuario] ?? null;
    }

    /** @return list<string> Na ordem de entrada. */
    public function membrosAtivos(): array
    {
        $ativos = array_keys(array_filter($this->membros, static fn (SituacaoNaMesa $s): bool => $s === SituacaoNaMesa::Ativa));

        return array_map('strval', $ativos);
    }

    /** @return list<array{usuario: string, situacao: SituacaoNaMesa}> Na ordem de entrada. */
    public function membros(): array
    {
        $membros = [];
        foreach ($this->membros as $usuario => $situacao) {
            $membros[] = ['usuario' => (string) $usuario, 'situacao' => $situacao];
        }

        return $membros;
    }

    /** @return list<ParticipanteLocal> */
    public function participantesLocais(): array
    {
        return array_values($this->participantesLocais);
    }

    public function participanteLocal(string $id): ?ParticipanteLocal
    {
        return $this->participantesLocais[$id] ?? null;
    }

    // Persistência: o relógio não é gravado; quem carrega a mesa religa o relógio.

    public function religar(Relogio $relogio): void
    {
        $this->relogio = $relogio;
        foreach ($this->partidas as $partida) {
            $partida->religar($relogio);
        }
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
        $dados['nome'] ??= '';
        $dados['criadaEm'] ??= null;
        $dados['lugares'] ??= null;
        foreach ($dados as $propriedade => $valor) {
            $this->{$propriedade} = $valor;
        }
    }

    // Regras

    private function exigirPartidaAtiva(): Partida
    {
        return $this->partidaAtiva() ?? throw new RegraViolada('A mesa não tem partida ativa.');
    }

    private function exigirMembroAtivo(string $usuario): void
    {
        if (!$this->membroAtivo($usuario)) {
            throw new RegraViolada("{$usuario} não está na mesa.");
        }
    }

    private function exigirAdmin(string $quem): void
    {
        if ($quem !== $this->admin) {
            throw new RegraViolada('Somente o admin pode realizar esta ação.');
        }
    }

    private function exigirAberta(): void
    {
        if ($this->situacao !== SituacaoMesa::Aberta) {
            throw new RegraViolada('A mesa está encerrada.');
        }
    }

    private function normalizarNome(string $nome): string
    {
        $nome = trim($nome);
        if (mb_strlen($nome) > self::TAMANHO_MAXIMO_DO_NOME) {
            throw new RegraViolada('O nome da mesa pode ter no máximo 40 caracteres.');
        }

        return $nome;
    }
}
