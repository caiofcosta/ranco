<?php

declare(strict_types=1);

namespace App\Providers;

use App\Conquista\ConcedeMedalhaDaPartida;
use App\Conquista\ConcedeTrofeuDaMesa;
use App\Dominio\Conquista\ConcessorDeMedalhas;
use App\Dominio\Conquista\ConcessorDeTrofeus;
use App\Dominio\Conquista\RepositorioDeMedalhas;
use App\Dominio\Conquista\RepositorioDeRevelacoes;
use App\Dominio\Conquista\RepositorioDeTrofeus;
use App\Dominio\Jogos\Labirinto\RepositorioMapasLabirinto;
use App\Dominio\Jogos\Labirinto\RepositorioSoloLabirinto;
use App\Dominio\Jogos\RepositorioDeDisponibilidadeDeJogos;
use App\Dominio\Plataforma\NotificadorDeMesa;
use App\Dominio\Plataforma\NotificadorDePessoa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Plataforma\RepositorioDeConvites;
use App\Dominio\Plataforma\RepositorioDeMesas;
use App\Dominio\Plataforma\RepositorioDeVisibilidade;
use App\Dominio\Plataforma\Transacao;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;
use App\Dominio\Relogio;
use App\Dominio\RelogioDoSistema;
use App\Dominio\Social\RepositorioSocial;
use App\Infra\Banco\CatalogoDeMapasLabirinto;
use App\Infra\Banco\DisponibilidadeDeJogosNoBanco;
use App\Infra\Banco\PdoRepositorioDeConvites;
use App\Infra\Banco\PdoRepositorioDeMesas;
use App\Infra\Banco\PdoRepositorioDeProvocacoes;
use App\Infra\Banco\PdoRepositorioSocial;
use App\Infra\Banco\RepositorioDeMedalhasNoBanco;
use App\Infra\Banco\RepositorioDeRevelacoesNoBanco;
use App\Infra\Banco\RepositorioDeTrofeusNoBanco;
use App\Infra\Banco\RepositorioDeVisibilidadeNoBanco;
use App\Infra\Banco\SoloLabirinto;
use App\Infra\Banco\TransacaoDoLaravel;
use App\Infra\RelogioDoLaravel;
use App\Mesa\NomesDaMesaNaRequisicao;
use App\Mesa\Tempo\NotificadorDeMesaNoLaravel;
use App\Mesa\Tempo\NotificadorDePessoaNoLaravel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Liga o domínio (regras do jogo em PHP puro) à infraestrutura do Laravel: os repositórios usam
 * a mesma conexão PDO do Laravel e as transações passam pelo DB::transaction.
 */
final class DominioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Relogio::class, RelogioDoSistema::class);
        $this->app->singleton(NomesDaMesaNaRequisicao::class);
        $this->app->singleton(Transacao::class, TransacaoDoLaravel::class);

        $this->app->singleton(RepositorioDeMesas::class, static fn (Application $app): PdoRepositorioDeMesas => new PdoRepositorioDeMesas(
            DB::connection()->getPdo(),
            $app->make(Relogio::class),
        ));
        $this->app->singleton(RepositorioDeConvites::class, static fn (Application $app): PdoRepositorioDeConvites => new PdoRepositorioDeConvites(
            DB::connection()->getPdo(),
            $app->make(Relogio::class),
        ));
        $this->app->singleton(RepositorioSocial::class, static fn (Application $app): PdoRepositorioSocial => new PdoRepositorioSocial(
            DB::connection()->getPdo(),
            $app->make(Relogio::class),
        ));
        $this->app->singleton(RepositorioDeVisibilidade::class, static fn (): RepositorioDeVisibilidadeNoBanco => new RepositorioDeVisibilidadeNoBanco(
            DB::connection()->getPdo(),
        ));
        $this->app->singleton(RepositorioDeProvocacoes::class, static fn (): PdoRepositorioDeProvocacoes => new PdoRepositorioDeProvocacoes(
            DB::connection()->getPdo(),
        ));

        $this->app->singleton(RepositorioMapasLabirinto::class, CatalogoDeMapasLabirinto::class);
        $this->app->singleton(RepositorioDeDisponibilidadeDeJogos::class, static fn (): DisponibilidadeDeJogosNoBanco => new DisponibilidadeDeJogosNoBanco(
            DB::connection()->getPdo(),
        ));
        $this->app->singleton(RepositorioSoloLabirinto::class, SoloLabirinto::class);
        $this->app->singleton(\App\Dominio\Jogos\Labirinto\MapasDisponiveisParaPartida::class, \App\Infra\Banco\MapasDisponiveisParaPartidaNoBanco::class);
        $this->app->when([\App\Dominio\Jogos\Labirinto\SoloLabirinto::class, \App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto::class])
            ->needs(Relogio::class)->give(RelogioDoLaravel::class);

        $this->app->singleton(ConcessorDeMedalhas::class, ConcedeMedalhaDaPartida::class);
        $this->app->singleton(RepositorioDeMedalhas::class, RepositorioDeMedalhasNoBanco::class);
        $this->app->singleton(ConcessorDeTrofeus::class, ConcedeTrofeuDaMesa::class);
        $this->app->singleton(RepositorioDeTrofeus::class, RepositorioDeTrofeusNoBanco::class);
        $this->app->singleton(RepositorioDeRevelacoes::class, RepositorioDeRevelacoesNoBanco::class);
        $this->app->singleton(NotificadorDeMesa::class, NotificadorDeMesaNoLaravel::class);
        $this->app->singleton(NotificadorDePessoa::class, NotificadorDePessoaNoLaravel::class);
        $this->app->singleton(Plataforma::class);
    }
}
