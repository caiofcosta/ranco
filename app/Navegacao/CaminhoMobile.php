<?php

declare(strict_types=1);

namespace App\Navegacao;

/** Hierarquia de telas, baseada nas rotas reais; nunca usa o histórico como destino. */
final class CaminhoMobile
{
    /** @return list<array{rotulo: string, url: ?string}> */
    public function itens(?string $titulo = null): array
    {
        $nome = request()->route()?->getName();
        if (! $nome || in_array($nome, ['home', 'dashboard', 'mobile.inicio'], true)) {
            return [];
        }
        $inicio = ['rotulo' => 'Início', 'url' => route(auth()->user()?->hasVerifiedEmail() ? 'mobile.inicio' : 'home')];
        $mais = ['rotulo' => 'Mais', 'url' => route('mobile.mais')];
        $solo = ['rotulo' => 'Jogar sozinho', 'url' => route('jogar.index')];
        $labirinto = ['rotulo' => 'Labirinto', 'url' => route('jogar.labirinto')];
        $mesas = ['rotulo' => 'Jogar juntos', 'url' => route('mobile.jogar-juntos')];
        $conta = ['rotulo' => 'Perfil e conta', 'url' => route('profile.edit')];
        $pais = [$inicio];
        $atual = $titulo ?: 'Página atual';

        if (str_starts_with($nome, 'mobile.')) {
            $atual = ['pedidos' => 'Pedidos', 'jogar-juntos' => 'Jogar juntos', 'mais' => 'Mais'][substr($nome, 7)] ?? $atual;
        } elseif ($nome === 'jogar.index') {
            $atual = 'Jogar sozinho';
        } elseif ($nome === 'jogar.labirinto') {
            $pais[] = $solo;
            $atual = 'Labirinto';
        } elseif (str_starts_with($nome, 'jogar.labirinto.')) {
            $pais = [$inicio, $solo, $labirinto];
            $atual = $nome === 'jogar.labirinto.resultado' ? 'Resultado' : 'Jogar';
        } elseif ($nome === 'painel.labirinto.previa') {
            $pais = [$inicio, $solo, $labirinto];
            $atual = 'Prévia';
        } elseif (str_starts_with($nome, 'mesas.')) {
            $pais[] = $mesas;
            $id = request()->route('id');
            if ($id && $nome !== 'mesas.mostrar') {
                $pais[] = ['rotulo' => 'Mesa', 'url' => route('mesas.mostrar', $id)];
            }
            $atual = match ($nome) {
                'mesas.formulario' => 'Criar mesa',
                'mesas.encerrar' => 'Encerrar mesa',
                'mesas.sair' => 'Sair da mesa',
                'mesas.partida.encerrar' => 'Encerrar partida',
                'mesas.jogar', 'mesas.jogo' => 'Partida',
                'mesas.assistir' => 'Assistir',
                default => 'Mesa',
            };
        } elseif ($nome === 'perfil.amigo') {
            $pais = [$inicio, $mais, ['rotulo' => 'Amigos', 'url' => route('amigos.index')]];
            $atual = 'Perfil da amizade';
        } elseif (in_array($nome, ['amigos.index', 'historico', 'perfil.mostrar', 'profile.edit', 'security.edit'], true)) {
            $pais[] = $mais;
            $atual = ['amigos.index' => 'Amigos', 'historico' => 'Histórico', 'perfil.mostrar' => 'Medalhas e troféus', 'profile.edit' => 'Perfil e conta', 'security.edit' => 'Configurações'][$nome];
        } elseif (in_array($nome, ['conta.participacoes-locais', 'settings.regras-aceitas', 'conta.excluir', 'password.confirm'], true)) {
            $pais = [$inicio, $mais, $conta];
            $atual = ['conta.participacoes-locais' => 'Participações locais', 'settings.regras-aceitas' => 'Regras aceitas', 'conta.excluir' => 'Excluir conta', 'password.confirm' => 'Confirmar senha'][$nome];
        } elseif (str_starts_with($nome, 'documentos.')) {
            $atual = ['documentos.termos' => 'Termos de uso', 'documentos.privacidade' => 'Privacidade', 'documentos.conduta' => 'Conduta'][$nome] ?? $atual;
        } elseif (str_starts_with($nome, 'convites.')) {
            $pais[] = ['rotulo' => 'Pedidos', 'url' => route('mobile.pedidos')];
            $atual = 'Convite de mesa';
        } elseif ($nome === 'amizade.convite') {
            if (auth()->user()?->hasVerifiedEmail()) {
                $pais = [$inicio, $mais, ['rotulo' => 'Amigos', 'url' => route('amigos.index')]];
            }
            $atual = 'Convite de amizade';
        } else {
            $atual = [
                'login' => 'Entrar', 'register' => 'Criar conta',
                'password.request' => 'Recuperar senha', 'password.reset' => 'Redefinir senha',
                'verification.notice' => 'Confirmar e-mail', 'acesso-por-link' => 'Entrar por link',
                'cadastro.confira-seu-email' => 'Confira seu e-mail', 'aceite.mostrar' => 'Aceitar as regras',
                'two-factor.login' => 'Confirmar acesso',
            ][$nome] ?? $atual;
            if (in_array($nome, ['password.request', 'password.reset', 'acesso-por-link', 'cadastro.confira-seu-email', 'two-factor.login'], true)) {
                $pais[] = ['rotulo' => 'Entrar', 'url' => route('login')];
            }
        }

        return [...$pais, ['rotulo' => $atual, 'url' => null]];
    }
}
