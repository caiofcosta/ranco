<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

use App\Dominio\CodigoDaRegra;
use App\Dominio\Jogos\Labirinto\MapasDisponiveisParaPartida;
use App\Dominio\Jogos\RepositorioDeDisponibilidadeDeJogos;
use App\Dominio\ConflitoDeConcorrencia;
use App\Dominio\Conquista\ConcessorDeMedalhas;
use App\Dominio\Conquista\ConcessorDeMedalhasNulo;
use App\Dominio\Conquista\ConcessorDeTrofeus;
use App\Dominio\Conquista\ConcessorDeTrofeusNulo;
use App\Dominio\Conquista\RepositorioDeMedalhas;
use App\Dominio\Conquista\RepositorioDeTrofeus;
use App\Dominio\Conquista\RepositorioDeRevelacoes;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\ParticipanteLocal;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\NaoEncontrado;
use App\Dominio\RegraViolada;
use App\Dominio\Relogio;
use App\Dominio\Social\Amizades;
use App\Dominio\Social\Convite;
use App\Dominio\Social\RepositorioSocial;
use Throwable;

/**
 * Regras que cruzam mesas e pessoas: até 5 mesas e uma jogada por vez, amizade, bloqueio, convites e
 * reconhecimento de participações locais. Toda alteração acontece dentro de uma transação, e
 * os prazos vencidos da mesa são processados sempre que ela é carregada.
 */
final class Plataforma
{
    public const LIMITE_DE_MESAS = 5;

    private Amizades $amizades;

    private readonly ConcessorDeMedalhas $medalhas;
    private readonly ConcessorDeTrofeus $trofeus;
    private readonly NotificadorDeMesa $notificador;
    private readonly RepositorioDeVisibilidade $visibilidade;
    private readonly NotificadorDePessoa $notificadorDePessoa;

    public function __construct(
        private readonly Relogio $relogio,
        private readonly RepositorioDeMesas $mesas,
        private readonly RepositorioDeConvites $convites,
        RepositorioSocial $social,
        private readonly Transacao $transacao,
        private readonly RepositorioDeDisponibilidadeDeJogos $jogos,
        ?ConcessorDeMedalhas $medalhas = null,
        ?ConcessorDeTrofeus $trofeus = null,
        private readonly ?RepositorioDeRevelacoes $revelacoes = null,
        ?NotificadorDeMesa $notificador = null,
        ?RepositorioDeVisibilidade $visibilidade = null,
        ?NotificadorDePessoa $notificadorDePessoa = null,
        private readonly ?MapasDisponiveisParaPartida $mapas = null,
    ) {
        $this->amizades = new Amizades($social);
        $this->medalhas = $medalhas ?? new ConcessorDeMedalhasNulo();
        $this->trofeus = $trofeus ?? new ConcessorDeTrofeusNulo();
        $this->notificador = $notificador ?? new NotificadorDeMesaNulo();
        $this->visibilidade = $visibilidade ?? new RepositorioDeVisibilidadeNulo();
        $this->notificadorDePessoa = $notificadorDePessoa ?? new NotificadorDePessoaNulo();
    }

    /** @return list<array{id: string, nome: string}> */
    public function resultadosPendentesDe(string $usuario): array
    {
        return $this->revelacoes?->pendentesDe($usuario) ?? [];
    }

    public function marcarResultadoVisto(string $usuario, string $mesaId): void
    {
        $this->revelacoes?->marcarVista($usuario, $mesaId, $this->relogio->agora());
    }

    // Mesas

    public function criarMesa(string $admin, string $nome = ''): Mesa
    {
        return $this->transacao->executar(function () use ($admin, $nome): Mesa {
            // A trava vem antes de qualquer leitura: no REPEATABLE READ a primeira leitura fixa a foto do banco.
            $this->mesas->travarPessoa($admin);
            $this->exigirVisivel($admin);
            $this->exigirVagaParaCriarMesa($admin);

            $mesa = Mesa::criar($this->mesas->novoId(), $admin, $this->relogio, $nome);
            $this->mesas->salvar($mesa);

            return $mesa;
        });
    }

    /** Lê a mesa; se algum prazo venceu desde a última gravação, grava o resultado. */
    public function mesa(string $id): Mesa
    {
        $gravou = false;
        $mesa = $this->transacao->executar(function () use ($id, &$gravou): Mesa {
            $gravou = false;
            $mesa = $this->mesas->buscar($id) ?? throw new NaoEncontrado('Mesa inexistente.');
            $partida = $mesa->partidaAtiva();
            if ($partida === null) {
                return $mesa;
            }

            $preparouMapas = $this->prepararMapasLegados($mesa);
            $eventosAntes = count($partida->eventos());
            $partida->processarPrazos();
            if ($preparouMapas || count($partida->eventos()) !== $eventosAntes) {
                $this->mesas->salvar($mesa);
                $gravou = true;
            }

            return $mesa;
        });
        $this->concederMedalhas($mesa);
        $this->concederTrofeus($mesa);
        // Leitura sem prazo vencido não avisa, para o render do cliente não gerar outro aviso.
        if ($gravou) {
            $this->avisarQueAMesaMudou($id);
        }

        return $mesa;
    }

    /** @return list<Mesa> Da mais recente para a mais antiga, com os prazos vencidos já processados. */
    public function mesasAbertasDe(string $usuario): array
    {
        $mesas = array_map(fn (Mesa $mesa): Mesa => $this->mesa($mesa->id), $this->mesas->mesasAbertasDe($usuario));
        usort($mesas, static fn (Mesa $a, Mesa $b): int => [$b->criadaEm(), $b->id] <=> [$a->criadaEm(), $a->id]);

        return $mesas;
    }

    /** Só as mesas que a pessoa administra consomem o limite; participar em mesa de terceiros não conta. */
    public function vagasDeMesa(string $usuario): int
    {
        return max(0, self::LIMITE_DE_MESAS - count($this->administradasDe($usuario)));
    }

    /** Membro ativo de mesa aberta, visível. Ações e a tela da mesa aberta passam por aqui. */
    public function mesaDoMembro(string $mesaId, string $pessoa): Mesa
    {
        $mesa = $this->mesa($mesaId);
        if (!$mesa->membroAtivo($pessoa)) {
            throw new NaoEncontrado('Mesa inexistente.');
        }
        $this->exigirVisivel($pessoa, 'Fique visível para entrar na mesa.');

        return $mesa;
    }

    /**
     * Igual ao mesaDoMembro mas sem rodar processarPrazos. Usado só pelo POST de resultado:
     * se o cliente envia a pontuação alguns milissegundos depois do fim do prazo, não queremos
     * que a leitura inicial da mesa já interrompa a vez antes de a ação gravar o resultado.
     */
    public function mesaDoMembroSemAvancarPrazo(string $mesaId, string $pessoa): Mesa
    {
        $mesa = $this->mesas->buscar($mesaId) ?? throw new NaoEncontrado('Mesa inexistente.');
        if (!$mesa->membroAtivo($pessoa)) {
            throw new NaoEncontrado('Mesa inexistente.');
        }
        $this->exigirVisivel($pessoa, 'Fique visível para entrar na mesa.');

        return $mesa;
    }

    public function mesaDoAdmin(string $mesaId, string $pessoa): Mesa
    {
        $mesa = $this->mesaDoMembro($mesaId, $pessoa);
        if ($mesa->admin() !== $pessoa) {
            throw new NaoEncontrado('Mesa inexistente.');
        }

        return $mesa;
    }

    /** Consulta: a mesa aberta segue `mesaDoMembro`; a encerrada fica para quem constou entre os membros. */
    public function mesaParaConsulta(string $mesaId, string $pessoa): Mesa
    {
        $mesa = $this->mesa($mesaId);
        if ($mesa->situacao() === SituacaoMesa::Encerrada) {
            if ($mesa->situacaoDe($pessoa) === null) {
                throw new NaoEncontrado('Mesa inexistente.');
            }

            return $mesa;
        }

        if (! $mesa->membroAtivo($pessoa)) {
            throw new NaoEncontrado('Mesa inexistente.');
        }
        $this->exigirVisivel($pessoa, 'Fique visível para entrar na mesa.');

        return $mesa;
    }

    /**
     * Uma jogada por vez: quem joga (a conta logada, inclusive o admin pela vez de um participante
     * local) não inicia jogada se já tem outra em andamento em qualquer outra mesa. `$escolher`
     * confere o pedido e devolve a partida e o participante da vez.
     *
     * @param callable(Mesa): array{0: Partida, 1: string} $escolher
     */
    public function iniciarTentativa(string $mesaId, string $quemJoga, callable $escolher): Mesa
    {
        $mesa = $this->transacao->executar(function () use ($mesaId, $quemJoga, $escolher): Mesa {
            // A pessoa é travada antes de qualquer leitura e antes da mesa, como em criar e aceitar.
            $this->mesas->travarPessoa($quemJoga);
            $this->exigirVisivel($quemJoga, 'Fique visível para entrar na mesa.');
            $mesa = $this->carregar($mesaId);
            [$partida, $participante] = $escolher($mesa);
            $jaIniciada = $partida->tentativaDe($partida->rodadaAtual(), $participante, $partida->desempateAtual()) !== null;
            if (!$jaIniciada) {
                if ($this->temJogadaEmAndamento($quemJoga, $mesa->id)) {
                    throw new RegraViolada('Você já está jogando em outra mesa. Termine aquela jogada primeiro.', null, CodigoDaRegra::JogadaEmOutraMesa);
                }
                $partida->iniciarTentativa($participante);
            }
            $this->mesas->salvar($mesa);

            return $mesa;
        });
        $this->concederMedalhas($mesa);
        $this->concederTrofeus($mesa);
        $this->avisarQueAMesaMudou($mesaId);

        return $mesa;
    }

    /** Jogada em andamento da conta, própria ou como admin pela vez de um participante local. */
    public function temJogadaEmAndamento(string $pessoa, ?string $excetoMesa = null): bool
    {
        foreach ($this->mesas->mesasAbertasDe($pessoa) as $mesa) {
            if ($mesa->id === $excetoMesa) {
                continue;
            }
            $partida = $mesa->partidaAtiva();
            if ($partida === null || $partida->situacao() !== SituacaoPartida::EmAndamento) {
                continue;
            }
            $jogaPor = $mesa->admin() === $pessoa
                ? [$pessoa, ...array_map(static fn ($local): string => $local->id, $mesa->participantesLocais())]
                : [$pessoa];
            foreach ($jogaPor as $participante) {
                if ($partida->temTentativaEmCurso($participante)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Executa uma ação sobre a mesa e grava o resultado. Se a mesa terminar encerrada,
     * os convites pendentes para ela são cancelados. A medalha é concedida depois da
     * gravação, para um erro no sorteio não desfazer a partida.
     *
     * @param callable(Mesa): mixed $acao
     */
    /**
     * @param bool $avisar false: não avisa a mesa (ex.: rascunho privado, que não muda o que os outros veem)
     * @param bool $antesDoPrazo true: roda a ação antes de processarPrazos. Usado só pelo POST de resultado
     *                           para que uma pontuação que chega milissegundos depois do prazo ainda seja
     *                           aceita (o cliente da Moedinha, por exemplo, encerra o jogo exatamente no fim
     *                           do prazo). Após a ação, processarPrazos ainda roda para limpar o resto.
     */
    public function naMesa(string $mesaId, callable $acao, bool $avisar = true, bool $antesDoPrazo = false): Mesa
    {
        $mesa = $this->transacao->executar(function () use ($mesaId, $acao, $antesDoPrazo): Mesa {
            if ($antesDoPrazo) {
                $mesa = $this->mesas->buscar($mesaId) ?? throw new NaoEncontrado('Mesa inexistente.');
                $this->prepararMapasLegados($mesa);
                $acao($mesa);
                $mesa->partidaAtiva()?->processarPrazos();
            } else {
                $mesa = $this->carregar($mesaId);
                $acao($mesa);
            }
            $this->mesas->salvar($mesa);

            if ($mesa->situacao() === SituacaoMesa::Encerrada) {
                foreach ($this->convites->pendentesDaMesa($mesaId) as $convite) {
                    $convite->cancelar();
                    $this->convites->salvar($convite);
                }
            }

            return $mesa;
        });
        $this->concederMedalhas($mesa);
        $this->concederTrofeus($mesa);
        if ($avisar) {
            $this->avisarQueAMesaMudou($mesaId);
        }

        return $mesa;
    }

    public function abandonarMesa(string $mesaId, string $usuario): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->abandonar($usuario));
        $this->avisarMesasDaPessoa($usuario);
    }

    public function encerrarMesa(string $mesaId, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->encerrar($por));
    }

    public function renomearMesa(string $mesaId, string $nome, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->renomear($nome, $por));
    }

    public function criarPartida(string $mesaId, ConfiguracaoPartida $configuracao, string $por): void
    {
        $this->exigirJogoDisponivel($configuracao->jogo);
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->novaPartida($configuracao, $por));
    }

    public function reconfigurarPartida(string $mesaId, ConfiguracaoPartida $configuracao, string $por): void
    {
        $this->exigirJogoDisponivel($configuracao->jogo);
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->reconfigurarPartida($configuracao, $por));
    }

    public function configurarLugares(string $mesaId, int $quantidade, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->configurarLugares($quantidade, $por));
    }

    public function iniciarPartida(string $mesaId, string $por): void
    {
        $this->naMesa($mesaId, function (Mesa $mesa) use ($por): void {
            $partida = $mesa->partidaAtiva();
            if ($partida !== null && $partida->situacao() === SituacaoPartida::EmConfiguracao) {
                $this->exigirJogoDisponivel($partida->configuracao()->jogo);
            }
            $mesa->iniciarPartida($por);
            $partida = self::partidaAtivaDa($mesa);
            if ($partida->configuracao()->jogo === 'labirinto' && $this->mapas !== null) {
                $partida->prepararMapasLabirinto($this->mapas->listar(), incluirRodadaAtual: true);
            }
        });
    }

    public function pausarPartida(string $mesaId, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => self::partidaAtivaDa($mesa)->pausar($por));
    }

    public function despausarPartida(string $mesaId, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => self::partidaAtivaDa($mesa)->despausar($por));
    }

    public function pularVez(string $mesaId, string $participante, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => self::partidaAtivaDa($mesa)->pularVez($participante, $por));
    }

    public function admitirNaPartida(string $mesaId, string $usuario, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->admitirNaPartida($usuario, $por));
    }

    public function removerDaPartida(string $mesaId, string $usuario, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->removerDaPartida($usuario, $por));
    }

    public function removerDaMesa(string $mesaId, string $usuario, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->removerDaMesa($usuario, $por));
        $this->avisarMesasDaPessoa($usuario);
    }

    public function encerrarPartida(string $mesaId, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => $mesa->encerrarPartida($por));
    }

    public function iniciarProximaRodada(string $mesaId, string $por): void
    {
        $this->naMesa($mesaId, static fn (Mesa $mesa) => self::partidaAtivaDa($mesa)->iniciarProximaRodada($por));
    }

    /** Pula as vezes vencidas de todas as partidas em andamento. Pensado para rodar por cron. */
    public function processarPrazosDeTodas(): int
    {
        $processadas = 0;
        foreach ($this->mesas->idsComPartidaEmAndamento() as $mesaId) {
            try {
                $this->mesa($mesaId);
                $processadas++;
            } catch (ConflitoDeConcorrencia) {
                // A mesa foi alterada ao mesmo tempo por outra requisição, que já processou os prazos.
            }
        }

        return $processadas;
    }

    // Amizade e bloqueio

    public function solicitarAmizade(string $de, string $para): void
    {
        $this->transacao->executar(function () use ($de, $para): void {
            $this->exigirVisivel($de);
            $this->amizades->solicitar($de, $para);
        });
    }

    public function aceitarAmizade(string $quem, string $de): void
    {
        $this->transacao->executar(function () use ($quem, $de): void {
            $this->exigirVisivel($quem);
            $this->amizades->aceitar($quem, $de);
        });
    }

    /** Desfazer amizade cancela convites pendentes entre os dois, mas não remove participações. */
    public function desfazerAmizade(string $quem, string $outro): void
    {
        $this->transacao->executar(function () use ($quem, $outro): void {
            $this->amizades->desfazer($quem, $outro);
            $this->cancelarConvitesEntre($quem, $outro);
        });
    }

    /** Bloquear cancela convites pendentes nas duas direções, mas não remove participações. */
    public function bloquear(string $quem, string $alvo): void
    {
        $this->transacao->executar(function () use ($quem, $alvo): void {
            $this->amizades->bloquear($quem, $alvo);
            $this->cancelarConvitesEntre($quem, $alvo);
        });
    }

    public function saoAmigos(string $a, string $b): bool
    {
        return $this->amizades->saoAmigos($a, $b);
    }

    public function bloqueou(string $quem, string $alvo): bool
    {
        return $this->amizades->bloqueou($quem, $alvo);
    }

    /** @return list<string> */
    public function amigosDe(string $usuario): array
    {
        return $this->amizades->amigosDe($usuario);
    }

    /**
     * Aceite de um convite de amizade enviado por e-mail: quem abriu o link vira amizade de quem
     * convidou, numa transação só. Bloqueio e auto-amizade continuam valendo; se já forem amigos,
     * não faz nada.
     */
    public function aceitarConviteDeAmizade(string $de, string $quem): void
    {
        $this->transacao->executar(function () use ($de, $quem): void {
            $this->exigirVisivel($quem);
            if ($this->amizades->saoAmigos($de, $quem)) {
                return;
            }
            $this->amizades->solicitar($de, $quem);
            $this->amizades->aceitar($quem, $de);
        });
    }

    /** @return list<string> */
    public function pedidosDeAmizadeRecebidos(string $usuario): array
    {
        return $this->amizades->pedidosRecebidos($usuario);
    }

    // Visibilidade

    /** Ter mesas na lista não impede; estar jogando impede. Invisível não interage com outras pessoas. */
    public function ficarInvisivel(string $pessoa): void
    {
        $mesas = $this->transacao->executar(function () use ($pessoa): array {
            $this->mesas->travarPessoa($pessoa);
            if ($this->temJogadaEmAndamento($pessoa)) {
                throw new RegraViolada('Termine sua jogada e saia da mesa para ficar invisível.', null, CodigoDaRegra::JogandoAgora);
            }
            $this->visibilidade->definir($pessoa, true);

            return $this->mesas->mesasAbertasDe($pessoa);
        });
        // Abas já abertas saem da presença: as da mesa pelo aviso da mesa; todas (inclusive a
        // Início de quem tem convite pendente) pelo aviso no canal da própria pessoa.
        foreach ($mesas as $mesa) {
            $this->avisarQueAMesaMudou($mesa->id);
        }
        try {
            $this->notificadorDePessoa->ficouInvisivel($pessoa);
        } catch (Throwable $erro) {
            if (function_exists('report')) {
                report($erro);
            }
        }
    }

    public function ficarVisivel(string $pessoa): void
    {
        $this->transacao->executar(fn () => $this->visibilidade->definir($pessoa, false));
        $this->avisarMesasDaPessoa($pessoa);
    }

    public function estaInvisivel(string $pessoa): bool
    {
        return $this->visibilidade->invisivel($pessoa);
    }

    // Convites

    public function convidar(string $mesaId, string $de, string $para): Convite
    {
        $convite = $this->transacao->executar(function () use ($mesaId, $de, $para): Convite {
            $mesa = $this->carregar($mesaId);
            if ($mesa->situacao() !== SituacaoMesa::Aberta) {
                throw new RegraViolada('A mesa está encerrada.', null, CodigoDaRegra::MesaEncerrada);
            }
            if ($de !== $mesa->admin()) {
                throw new RegraViolada('Somente o admin convida para a mesa.', null, CodigoDaRegra::SomenteAdminConvida);
            }
            if (!$this->amizades->saoAmigos($de, $para)) {
                throw new RegraViolada('O convite exige amizade aceita.', null, CodigoDaRegra::ConviteExigeAmizade);
            }
            if ($this->amizades->bloqueou($para, $de)) {
                throw new RegraViolada('Convite não permitido: a pessoa bloqueou você.', null, CodigoDaRegra::ConvidadoBloqueou);
            }
            if ($mesa->membroAtivo($para)) {
                throw new RegraViolada("{$para} já está na mesa.", null, CodigoDaRegra::JaEstaNaMesa);
            }
            foreach ($this->convites->pendentesDaMesa($mesaId) as $pendente) {
                if ($pendente->para === $para) {
                    throw new RegraViolada("{$para} já tem um convite pendente para esta mesa.", null, CodigoDaRegra::ConviteJaPendente);
                }
            }

            $convite = new Convite($this->convites->novoId(), $mesaId, $de, $para);
            $this->convites->salvar($convite);

            return $convite;
        });
        $this->avisarQueAMesaMudou($mesaId);

        return $convite;
    }

    /** Aceitar exige vaga entre as 5 mesas; sem vaga, o convite continua pendente. */
    public function aceitarConvite(string $conviteId, string $quem): void
    {
        $mesaId = $this->transacao->executar(function () use ($conviteId, $quem): string {
            $this->mesas->travarPessoa($quem);
            $convite = $this->conviteDe($conviteId, $quem);
            if (!$convite->pendente()) {
                throw new RegraViolada('O convite não está mais pendente.', null, CodigoDaRegra::ConviteNaoPendente);
            }
            $mesa = $this->carregar($convite->mesaId);
            if ($mesa->situacao() !== SituacaoMesa::Aberta) {
                throw new RegraViolada('A mesa do convite foi encerrada.', null, CodigoDaRegra::MesaEncerrada);
            }
            $this->exigirVisivel($quem);

            $mesa->adicionarMembro($quem);
            $convite->aceitar();
            $this->mesas->salvar($mesa);
            $this->convites->salvar($convite);

            return $mesa->id;
        });
        $this->avisarQueAMesaMudou($mesaId);
        $this->avisarMesasDaPessoa($quem);
    }

    public function recusarConvite(string $conviteId, string $quem): void
    {
        $mesaId = $this->transacao->executar(function () use ($conviteId, $quem): string {
            $convite = $this->conviteDe($conviteId, $quem);
            $convite->recusar();
            $this->convites->salvar($convite);

            return $convite->mesaId;
        });
        $this->avisarQueAMesaMudou($mesaId);
        $this->avisarMesasDaPessoa($quem);
    }

    public function cancelarConvite(string $conviteId, string $por): void
    {
        $destinatario = $this->convite($conviteId)->para;
        $mesaId = $this->transacao->executar(function () use ($conviteId, $por): string {
            $convite = $this->convite($conviteId);
            if ($por !== $convite->de) {
                throw new RegraViolada('Somente quem convidou pode cancelar o convite.', null, CodigoDaRegra::SomenteQuemConvidouCancela);
            }
            $convite->cancelar();
            $this->convites->salvar($convite);

            return $convite->mesaId;
        });
        $this->avisarQueAMesaMudou($mesaId);
        $this->avisarMesasDaPessoa($destinatario);
    }

    public function convite(string $id): Convite
    {
        return $this->convites->buscar($id) ?? throw new NaoEncontrado('Convite inexistente.');
    }

    /** @return list<Convite> */
    public function convitesPendentesPara(string $usuario): array
    {
        return $this->convites->pendentesPara($usuario);
    }

    /** @return list<Convite> */
    public function convitesPendentesDaMesa(string $mesaId): array
    {
        $this->carregar($mesaId);

        return $this->convites->pendentesDaMesa($mesaId);
    }

    // Participação local

    public function cadastrarParticipanteLocal(string $mesaId, string $nome, string $email, string $por): string
    {
        $id = '';
        $this->naMesa($mesaId, static function (Mesa $mesa) use ($nome, $email, $por, &$id): void {
            $id = $mesa->cadastrarParticipanteLocal($nome, $email, $por);
        });

        return $id;
    }

    /** @return list<ParticipanteLocal> */
    public function participacoesLocaisPendentes(string $email): array
    {
        return $this->mesas->participantesLocaisDoEmail($email);
    }

    public function reconhecerParticipacaoLocal(string $idLocal, string $usuario, string $emailDoUsuario): void
    {
        $this->naMesa(
            $this->mesaDoParticipanteLocal($idLocal),
            fn (Mesa $mesa) => $this->participanteLocalNa($mesa, $idLocal)->reconhecer($usuario, $emailDoUsuario),
        );
    }

    public function rejeitarParticipacaoLocal(string $idLocal, string $emailDoUsuario): void
    {
        $this->naMesa(
            $this->mesaDoParticipanteLocal($idLocal),
            fn (Mesa $mesa) => $this->participanteLocalNa($mesa, $idLocal)->rejeitar($emailDoUsuario),
        );
    }

    /** @return list<string> Participações locais que já entraram no histórico oficial do usuário. */
    public function participacoesReconhecidasDe(string $usuario): array
    {
        return array_map(
            static fn (ParticipanteLocal $p): string => $p->id,
            $this->mesas->participantesLocaisReconhecidosPor($usuario),
        );
    }

    /**
     * Medalhas da conta e apenas das participações locais já reconhecidas por ela.
     *
     * @param list<string>|null $participacoesReconhecidas Identificadores consultados nesta requisição, para esta conta.
     * @return list<array{id: int, colocacao: int, empate: bool, jogo: string, mesa: string, frase: ?string, concedidaEm: \DateTimeImmutable, partidaCriadaEm: ?\DateTimeImmutable}>
     */
    public function medalhasDe(string $usuario, RepositorioDeMedalhas $medalhas, ?array $participacoesReconhecidas = null): array
    {
        return $medalhas->dasPessoas([
            $usuario,
            ...($participacoesReconhecidas ?? $this->participacoesReconhecidasDe($usuario)),
        ]);
    }

    /**
     * Troféus da conta e somente das participações locais reconhecidas por ela.
     *
     * @param list<string>|null $participacoesReconhecidas Identificadores consultados nesta requisição, para esta conta.
     */
    public function trofeusDe(string $usuario, RepositorioDeTrofeus $trofeus, ?array $participacoesReconhecidas = null): array
    {
        return $trofeus->dasPessoas([
            $usuario,
            ...($participacoesReconhecidas ?? $this->participacoesReconhecidasDe($usuario)),
        ]);
    }

    /** Consulta de uma mesa ja carregada e autorizada pela tela. */
    public function trofeusDaMesa(Mesa $mesa, RepositorioDeTrofeus $trofeus): array
    {
        return $mesa->situacao() === SituacaoMesa::Encerrada ? $trofeus->daMesa($mesa->id) : [];
    }

    // Apoio

    /** Fora da transacao; uma falha nao desfaz a mesa e a proxima leitura tenta novamente. */
    private function concederTrofeus(Mesa $mesa): void
    {
        try {
            $this->trofeus->concederPendentes($mesa);
        } catch (Throwable $erro) {
            if (function_exists('report')) {
                report($erro);
            }
        }
    }

    /**
     * Fora da transação: se o transporte falhar, a ação continua gravada e o erro é registrado.
     * O cliente relê a mesa ao reconectar, o que compensa o sinal perdido numa queda.
     */
    private function avisarQueAMesaMudou(string $mesaId): void
    {
        try {
            $this->notificador->mesaMudou($mesaId);
        } catch (Throwable $erro) {
            if (function_exists('report')) {
                report($erro);
            }
        }
    }

    /**
     * Fora da transação da mesa: se a frase ou a gravação falhar, a partida continua gravada
     * e a medalha é tentada de novo na próxima leitura.
     */
    private function concederMedalhas(Mesa $mesa): void
    {
        try {
            $this->medalhas->concederPendentes($mesa);
        } catch (Throwable $erro) {
            if (function_exists('report')) {
                report($erro);
            }
        }
    }

    /** Carrega a mesa e já processa os prazos vencidos da partida em andamento. */
    private static function partidaAtivaDa(Mesa $mesa): Partida
    {
        return $mesa->partidaAtiva() ?? throw new RegraViolada('A mesa não tem partida ativa.');
    }

    private function carregar(string $mesaId): Mesa
    {
        $mesa = $this->mesas->buscar($mesaId) ?? throw new NaoEncontrado('Mesa inexistente.');
        $this->prepararMapasLegados($mesa);
        $mesa->partidaAtiva()?->processarPrazos();

        return $mesa;
    }

    /** A adoção do catálogo nunca troca o desafio de uma rodada que já estava aberta. */
    private function prepararMapasLegados(Mesa $mesa): bool
    {
        $partida = $mesa->partidaAtiva();
        if ($this->mapas === null || $partida === null || $partida->situacao() !== SituacaoPartida::EmAndamento
            || $partida->configuracao()->jogo !== 'labirinto' || $partida->temMapasLabirinto()) {
            return false;
        }
        $versoes = $this->mapas->listar();
        if ($versoes === []) {
            return false;
        }
        $partida->prepararMapasLabirinto($versoes);

        return true;
    }

    private function exigirVisivel(string $pessoa, string $mensagem = 'Fique visível para interagir com outras pessoas.'): void
    {
        if ($this->visibilidade->invisivel($pessoa)) {
            throw new RegraViolada($mensagem, null, CodigoDaRegra::Invisivel);
        }
    }

    private function exigirJogoDisponivel(string $jogo): void
    {
        if (! $this->jogos->estaDisponivel($jogo)) {
            throw new RegraViolada('Este jogo não está disponível.');
        }
    }

    /** Mesas abertas que a pessoa administra, sem processar prazos: a leitura do limite não grava nada. */
    private function administradasDe(string $usuario): array
    {
        return array_values(array_filter(
            $this->mesas->mesasAbertasDe($usuario),
            static fn (Mesa $mesa): bool => $mesa->admin() === $usuario,
        ));
    }

    private function exigirVagaParaCriarMesa(string $admin): void
    {
        if (count($this->administradasDe($admin)) >= self::LIMITE_DE_MESAS) {
            throw new RegraViolada(
                'Você já administra '.self::LIMITE_DE_MESAS.' mesas, o máximo. Encerre uma para criar outra.',
                null,
                CodigoDaRegra::LimiteDeMesas,
            );
        }
    }

    /** Fora da transação, como os avisos de mesa: a Início da pessoa recarrega a própria lista. */
    private function avisarMesasDaPessoa(string $pessoa): void
    {
        try {
            $this->notificadorDePessoa->mesasDaPessoaMudaram($pessoa);
        } catch (Throwable $erro) {
            if (function_exists('report')) {
                report($erro);
            }
        }
    }

    private function conviteDe(string $conviteId, string $quem): Convite
    {
        $convite = $this->convite($conviteId);
        if ($convite->para !== $quem) {
            throw new RegraViolada('O convite é destinado a outra pessoa.', null, CodigoDaRegra::ConviteDeOutraPessoa);
        }

        return $convite;
    }

    private function cancelarConvitesEntre(string $a, string $b): void
    {
        foreach ($this->convites->pendentesEntre($a, $b) as $convite) {
            $convite->cancelar();
            $this->convites->salvar($convite);
        }
    }

    private function mesaDoParticipanteLocal(string $idLocal): string
    {
        $mesaId = strstr($idLocal, ':local:', true);

        return $mesaId === false ? throw new NaoEncontrado('Participação local inexistente.') : $mesaId;
    }

    private function participanteLocalNa(Mesa $mesa, string $idLocal): ParticipanteLocal
    {
        return $mesa->participanteLocal($idLocal) ?? throw new NaoEncontrado('Participação local inexistente.');
    }
}
