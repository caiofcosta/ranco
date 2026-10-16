<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto;
use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Jogos\Labirinto\ImportacaoMapaLabirinto;
use App\Dominio\RegraViolada;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use UnitEnum;

/** @phpstan-import-type Mapa from CatalogoDeMapasLabirinto */
class MapasLabirinto extends Page
{
    protected static ?string $slug = 'jogos/labirinto/mapas';

    protected static ?string $title = 'Mapas do Labirinto';

    protected static ?string $navigationLabel = 'Labirinto · Mapas';

    protected static string|UnitEnum|null $navigationGroup = 'Jogos';

    protected string $view = 'filament.pages.mapas-labirinto';

    #[Locked]
    public ?int $mapaId = null;

    #[Locked]
    public int $revisao = 0;

    #[Locked]
    public bool $ativo = false;

    #[Locked]
    public ?int $versaoPublicada = null;

    /** @var list<string> */
    #[Locked]
    public array $linhas = [];

    /** @var list<list<string>> */
    #[Locked]
    public array $historico = [];

    public string $nome = '';

    public string $ferramenta = '#';

    public string $jsonImportacao = '';

    #[Locked]
    public ?int $mapaRepetidoId = null;

    public function importar(): void
    {
        $this->resetErrorBag();
        $this->mapaRepetidoId = null;
        try {
            $dados = ImportacaoMapaLabirinto::ler($this->jsonImportacao);
            $conflito = app(CatalogoDeMapasLabirinto::class)->conflito(null, $dados['nome'], $dados['linhas']);
            if ($conflito !== null) {
                $this->mapaRepetidoId = $conflito['id'];
                throw new RegraViolada($conflito['mensagem']);
            }
            $json = $this->jsonImportacao;
            $this->novo();
            $this->jsonImportacao = $json;
            $this->nome = $dados['nome'];
            $this->linhas = $dados['linhas'];
            Notification::make()->title('Mapa carregado no editor. Revise antes de salvar.')->success()->send();
        } catch (RegraViolada $erro) {
            $this->addError('jsonImportacao', $erro->getMessage());
        }
    }

    public static function canAccess(): bool
    {
        $usuario = auth()->user();

        return $usuario instanceof User && $usuario->canAccessPanel(Filament::getPanel('painel'));
    }

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        $this->novo();
    }

    public function novo(): void
    {
        $this->reset('mapaId', 'revisao', 'ativo', 'versaoPublicada', 'historico', 'nome', 'ferramenta', 'mapaRepetidoId', 'jsonImportacao');
        $this->linhas = DesenhoLabirinto::vazio();
        $this->resetErrorBag();
    }

    public function abrir(int $id): void
    {
        $this->carregar(app(CatalogoDeMapasLabirinto::class)->buscar($id));
    }

    public function pintar(int $linha, int $coluna): void
    {
        if ($linha < 1 || $linha > 11 || $coluna < 1 || $coluna > 11
            || ! in_array($this->ferramenta, ['#', '.', 'I', 'C'], true)) {
            return;
        }
        if ($this->linhas[$linha][$coluna] === $this->ferramenta) {
            return;
        }
        $this->historico[] = $this->linhas;
        $this->historico = array_slice($this->historico, -50);

        if (in_array($this->ferramenta, ['I', 'C'], true)) {
            $this->linhas = array_map(
                fn (string $texto): string => str_replace($this->ferramenta, '.', $texto),
                $this->linhas,
            );
        }
        $this->linhas = array_values(array_replace($this->linhas, [
            $linha => substr_replace($this->linhas[$linha], $this->ferramenta, $coluna, 1),
        ]));
        $this->resetErrorBag();
    }

    public function desfazer(): void
    {
        $anterior = array_pop($this->historico);
        if ($anterior !== null) {
            $this->linhas = $anterior;
            $this->resetErrorBag();
        }
    }

    public function duplicar(): void
    {
        $this->mapaId = null;
        $this->revisao = 0;
        $this->ativo = false;
        $this->versaoPublicada = null;
        $this->historico = [];
        $this->nome = mb_substr($this->nome, 0, 72).' (cópia)';
        $this->mapaRepetidoId = null;
        $this->resetErrorBag();
    }

    public function validarTeste(): bool
    {
        $this->resetErrorBag();
        try {
            DesenhoLabirinto::validarPublicacao($this->linhas);

            return true;
        } catch (RegraViolada $erro) {
            $this->addError('mapa', $erro->getMessage());

            return false;
        }
    }

    public function salvar(bool $publicar = false): void
    {
        $this->executar(function () use ($publicar): void {
            /** @var User $usuario */
            $usuario = auth()->user();
            $mapa = app(CatalogoDeMapasLabirinto::class)->salvar(
                $this->mapaId, $this->revisao, $this->nome, $this->linhas,
                $publicar, (int) $usuario->id,
            );
            $this->carregar($mapa);
            Notification::make()->title($publicar ? 'Versão publicada e mapa ativado.' : 'Rascunho salvo.')
                ->success()->send();
        });
    }

    public function alternarAtivo(): void
    {
        $id = $this->mapaId;
        if ($id === null) {
            return;
        }
        $this->executar(function () use ($id): void {
            $mapa = app(CatalogoDeMapasLabirinto::class)->alternarAtivo($id, $this->revisao);
            $this->ativo = $mapa['ativo'];
            $this->revisao = $mapa['revisao'];
            Notification::make()->title($this->ativo ? 'Mapa ativado.' : 'Mapa desativado.')
                ->success()->send();
        });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $catalogo = app(CatalogoDeMapasLabirinto::class);

        return [
            'mapas' => $catalogo->listar(),
            'versoes' => $this->mapaId === null ? [] : $catalogo->versoes($this->mapaId),
        ];
    }

    /** @param Mapa $mapa */
    private function carregar(array $mapa): void
    {
        $this->mapaId = $mapa['id'];
        $this->revisao = $mapa['revisao'];
        $this->nome = $mapa['nome'];
        $this->linhas = $mapa['linhas'];
        $this->ativo = $mapa['ativo'];
        $this->versaoPublicada = $mapa['versao_publicada'];
        $this->historico = [];
        $this->mapaRepetidoId = null;
        $this->resetErrorBag();
    }

    /** @param callable(): void $acao */
    private function executar(callable $acao): void
    {
        $this->resetErrorBag();
        try {
            $acao();
        } catch (RegraViolada $erro) {
            $this->addError('mapa', $erro->getMessage());
        }
    }
}
