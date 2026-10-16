<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use App\Dominio\ConflitoDeConcorrencia;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\ParticipanteLocal;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Mesa\SituacaoNaMesa;
use App\Dominio\Mesa\SituacaoReconhecimento;
use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\Criterio;
use App\Dominio\Partida\Evento;
use App\Dominio\Partida\MotivoEncerramento;
use App\Dominio\Partida\Origem;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoParticipacao;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Partida\Tentativa;
use App\Dominio\Partida\TipoEvento;
use App\Dominio\Plataforma\RepositorioDeMesas;
use App\Dominio\Relogio;
use UnexpectedValueException;
use WeakMap;

/**
 * A mesa inteira (partidas, tentativas, participantes locais) é gravada serializada na coluna
 * `estado`. As tabelas membros_mesa, participantes_locais e eventos_partida são projeções
 * regravadas a cada salvamento, usadas para consultas e para garantir a mesa única.
 */
final class PdoRepositorioDeMesas implements RepositorioDeMesas
{
    private const CLASSES_PERMITIDAS = [
        Mesa::class,
        ParticipanteLocal::class,
        SituacaoMesa::class,
        SituacaoNaMesa::class,
        SituacaoReconhecimento::class,
        Partida::class,
        \App\Dominio\Jogos\Labirinto\MapasDaPartida::class,
        ConfiguracaoPartida::class,
        Tentativa::class,
        Evento::class,
        Criterio::class,
        MotivoEncerramento::class,
        Origem::class,
        SituacaoParticipacao::class,
        SituacaoPartida::class,
        SituacaoTentativa::class,
        ModoExecucao::class,
        TipoEvento::class,
        DateTimeImmutable::class,
        DateTimeZone::class,
    ];

    /** @var WeakMap<Mesa, int> Versão de cada mesa carregada ou gravada por este repositório. */
    private WeakMap $versoes;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Relogio $relogio,
    ) {
        $this->versoes = new WeakMap();
    }

    public function novoId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function buscar(string $id): ?Mesa
    {
        return $this->ler($id, $this->pdo->inTransaction());
    }

    private function ler(string $id, bool $travar): ?Mesa
    {
        $bloqueio = $travar ? ' FOR UPDATE' : '';
        $consulta = $this->pdo->prepare("SELECT estado, versao FROM mesas WHERE id = ?{$bloqueio}");
        $consulta->execute([$id]);
        $linha = $consulta->fetch();
        if ($linha === false) {
            return null;
        }

        $mesa = unserialize($linha['estado'], ['allowed_classes' => self::CLASSES_PERMITIDAS]);
        if (!$mesa instanceof Mesa) {
            throw new UnexpectedValueException("Estado gravado da mesa {$id} é inválido.");
        }

        $mesa->religar($this->relogio);
        $this->versoes[$mesa] = (int) $linha['versao'];

        return $mesa;
    }

    public function salvar(Mesa $mesa): void
    {
        $estado = serialize($mesa);
        $emAndamento = $mesa->partidaAtiva()?->situacao() === SituacaoPartida::EmAndamento ? 1 : 0;
        $agora = $this->relogio->agora()->format(FormatoDeData::PADRAO);
        $encerradaEm = $mesa->situacao() === SituacaoMesa::Encerrada ? $agora : null;

        if (isset($this->versoes[$mesa])) {
            $versao = $this->versoes[$mesa];
            $comando = $this->pdo->prepare(
                'UPDATE mesas SET situacao = ?, partida_em_andamento = ?, estado = ?, nome = ?, versao = versao + 1, atualizada_em = ?, encerrada_em = COALESCE(encerrada_em, ?)
                 WHERE id = ? AND versao = ?',
            );
            $comando->bindValue(1, $mesa->situacao()->value);
            $comando->bindValue(2, $emAndamento, PDO::PARAM_INT);
            $comando->bindValue(3, $estado, PDO::PARAM_LOB);
            $comando->bindValue(4, $mesa->nome() === '' ? null : $mesa->nome());
            $comando->bindValue(5, $agora);
            $comando->bindValue(6, $encerradaEm);
            $comando->bindValue(7, $mesa->id);
            $comando->bindValue(8, $versao, PDO::PARAM_INT);
            $comando->execute();
            if ($comando->rowCount() === 0) {
                throw new ConflitoDeConcorrencia('A mesa foi alterada por outra operação. Tente de novo.');
            }
            $this->versoes[$mesa] = $versao + 1;
        } else {
            $comando = $this->pdo->prepare(
                'INSERT INTO mesas (id, admin, situacao, partida_em_andamento, estado, nome, versao, atualizada_em, encerrada_em, criada_em)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            );
            $comando->bindValue(1, $mesa->id);
            $comando->bindValue(2, $mesa->admin());
            $comando->bindValue(3, $mesa->situacao()->value);
            $comando->bindValue(4, $emAndamento, PDO::PARAM_INT);
            $comando->bindValue(5, $estado, PDO::PARAM_LOB);
            $comando->bindValue(6, $mesa->nome() === '' ? null : $mesa->nome());
            $comando->bindValue(7, $agora);
            $comando->bindValue(8, $encerradaEm);
            $comando->bindValue(9, $mesa->criadaEm()?->setTimezone(new DateTimeZone('UTC'))->format(FormatoDeData::PADRAO));
            $comando->execute();
            $this->versoes[$mesa] = 1;
        }

        $this->gravarMembros($mesa);
        $this->gravarParticipantesLocais($mesa);
        $this->gravarEventos($mesa);
    }

    public function mesasAbertasDe(string $usuario): array
    {
        $consulta = $this->pdo->prepare('SELECT mesa_id FROM membros_mesa WHERE usuario_ativo = ?');
        $consulta->execute([$usuario]);

        return array_values(array_filter(array_map(
            // Só leitura, sem trava: ações da mesma pessoa já passam por travarPessoa.
            fn (string $mesaId): ?Mesa => $this->ler($mesaId, false),
            array_map('strval', $consulta->fetchAll(PDO::FETCH_COLUMN)),
        )));
    }

    public function travarPessoa(string $usuario): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$usuario]);
        }
    }

    public function idsComPartidaEmAndamento(): array
    {
        $consulta = $this->pdo->query('SELECT id FROM mesas WHERE partida_em_andamento = 1 ORDER BY atualizada_em');

        return array_map('strval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    }

    public function participantesLocaisDoEmail(string $email): array
    {
        return $this->participantesLocais(
            'SELECT id, mesa_id FROM participantes_locais WHERE email = ? AND situacao = ?',
            [mb_strtolower($email), SituacaoReconhecimento::Pendente->value],
        );
    }

    public function participantesLocaisReconhecidosPor(string $usuario): array
    {
        return $this->participantesLocais(
            'SELECT id, mesa_id FROM participantes_locais WHERE usuario = ? AND situacao = ?',
            [$usuario, SituacaoReconhecimento::Reconhecida->value],
        );
    }

    private function gravarMembros(Mesa $mesa): void
    {
        $this->pdo->prepare('DELETE FROM membros_mesa WHERE mesa_id = ?')->execute([$mesa->id]);

        $insercao = $this->pdo->prepare(
            'INSERT INTO membros_mesa (mesa_id, usuario, situacao, usuario_ativo) VALUES (?, ?, ?, ?)',
        );
        foreach ($mesa->membros() as ['usuario' => $usuario, 'situacao' => $situacao]) {
            // usuario_ativo: preenchido enquanto a conta é membro ativo de mesa aberta (consulta das mesas da pessoa).
            $ativoNaMesa = $mesa->membroAtivo($usuario) && $mesa->participanteLocal($usuario) === null;
            $insercao->execute([$mesa->id, $usuario, $situacao->value, $ativoNaMesa ? $usuario : null]);
        }
    }

    private function gravarParticipantesLocais(Mesa $mesa): void
    {
        $gravacao = $this->pdo->prepare(
            'INSERT INTO participantes_locais (id, mesa_id, nome, email, situacao, usuario) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), usuario = VALUES(usuario)',
        );
        foreach ($mesa->participantesLocais() as $participante) {
            $gravacao->execute([
                $participante->id,
                $mesa->id,
                $participante->nome,
                $participante->email,
                $participante->situacao->value,
                $participante->usuario,
            ]);
        }
    }

    private function gravarEventos(Mesa $mesa): void
    {
        $consulta = $this->pdo->prepare(
            'SELECT partida, MAX(sequencia) AS ultima FROM eventos_partida WHERE mesa_id = ? GROUP BY partida',
        );
        $consulta->execute([$mesa->id]);
        $ultimos = [];
        foreach ($consulta->fetchAll() as $linha) {
            $ultimos[(int) $linha['partida']] = (int) $linha['ultima'];
        }

        $insercao = $this->pdo->prepare(
            'INSERT INTO eventos_partida (mesa_id, partida, sequencia, tipo, origem, participante, rodada, ocorrido_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($mesa->partidas() as $indice => $partida) {
            $numero = $indice + 1;
            $eventos = $partida->eventos();
            for ($sequencia = ($ultimos[$numero] ?? 0) + 1; $sequencia <= count($eventos); $sequencia++) {
                $evento = $eventos[$sequencia - 1];
                $insercao->execute([
                    $mesa->id,
                    $numero,
                    $sequencia,
                    $evento->tipo->value,
                    $evento->origem->value,
                    $evento->participante,
                    $evento->rodada,
                    $evento->em->setTimezone(new DateTimeZone('UTC'))->format(FormatoDeData::PADRAO),
                ]);
            }
        }
    }

    /**
     * @param list<string> $parametros
     * @return list<ParticipanteLocal>
     */
    private function participantesLocais(string $sql, array $parametros): array
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);

        $encontrados = [];
        foreach ($consulta->fetchAll() as $linha) {
            $participante = $this->buscar((string) $linha['mesa_id'])?->participanteLocal((string) $linha['id']);
            if ($participante !== null) {
                $encontrados[] = $participante;
            }
        }

        return $encontrados;
    }
}
