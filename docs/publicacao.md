# Implantação e segurança — edição de portfólio

Este documento reúne cuidados gerais para executar ou implantar a edição do RANÇO. **Não é um registro de infraestrutura privada nem uma afirmação de que todos os controles abaixo foram verificados em produção.**

## Desenvolvimento local

A instalação reproduzível está descrita no [README](../README.md).

- Use `.env.example` apenas como base para criar um `.env` local.
- Não versione arquivos `.env`, credenciais, logs, backups de banco ou chaves privadas.
- As configurações padrão de desenvolvimento, incluindo `APP_DEBUG=true`, **não são adequadas para uma instância pública**.
- Altere as portas configuradas no `.env` quando houver conflito com outros serviços.

## Preparação de produção

Antes de publicar uma instância, verifique:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` correto e `APP_KEY` exclusiva.
- Banco de dados com usuário e senha fortes, **sem exposição direta à internet**.
- Configuração de sessão, cookies e HTTPS compatíveis com o domínio.
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` e valores `VITE_REVERB_*` adequados ao ambiente.
- Restrições de origem do Reverb e autorização dos canais privados.
- `TRUSTED_PROXIES` limitado aos proxies efetivamente utilizados, se houver.
- Transporte de e-mail configurado e testado, sem credenciais no código.
- `RANCO_ADMINISTRADORES` contendo somente contas autorizadas.
- Processos do servidor, fila, agendador e Reverb iniciando corretamente após reinício.
- Backups do banco, retenção de logs, monitoramento e procedimento de recuperação.
- Política de privacidade, exclusão de contas e tratamento de dados pessoais revisados para a implantação real.

O projeto inclui arquivos opcionais de produção: `Dockerfile.producao`, `docker-compose.producao.yml` e `producao.sh`. Eles exigem revisão e configuração específica antes de uso; **não execute diretamente com credenciais ou dados reais sem validar seu conteúdo**.

## Validação após a implantação

1. Confirmar acesso à página inicial e funcionamento da navegação em dispositivo móvel.
2. Validar criação e autenticação de contas, links de e-mail e recuperação de acesso.
3. Confirmar restrição de acesso ao painel e ao Pulse.
4. Abrir duas sessões autenticadas, criar uma mesa e testar uma partida do Labirinto.
5. Validar atualização em tempo real e conexão WebSocket segura.
6. Verificar execução de filas, tarefas agendadas e registros de erro.
7. Realizar auditoria de dependências e testes apropriados ao ambiente.
8. Testar o procedimento de restauração de backup.

## Atualizações e recuperação

O script `producao.sh` realiza operações sobre containers, dependências e banco. Antes de qualquer atualização real:

- Examine o diff e o status do Git; não inclua arquivos locais não rastreados por engano.
- Revise migrações e seeders; alterações em dados podem ser irreversíveis.
- Confirme as variáveis de ambiente antes de compilar assets com valores `VITE_*`.
- Tenha backup e plano de recuperação verificados.
- Não presuma rollback automático: a recuperação exige procedimento próprio.

## Antes de tornar este repositório público

- Revise **todos os arquivos** que serão expostos, inclusive imagens, fixtures, documentação e configurações.
- Verifique dados pessoais, credenciais, tokens, URLs privadas, nomes internos e informações operacionais.
- **Audite também o histórico de commits.** Apagar conteúdo na versão atual não apaga cópias que permanecem em commits anteriores.
- Caso existam segredos em commits antigos, revogue/rotacione esses segredos e considere publicar um repositório novo com histórico limpo.
- Mantenha a licença e a descrição do projeto coerentes: este recorte atualmente utiliza licença proprietária.

Este checklist não substitui um teste de segurança do sistema em execução.
