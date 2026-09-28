# OpenCode Orchestration Policy

Este projeto usa um agente principal em GPT-5.6 Luna e subagentes especializados.

O objetivo e maximizar qualidade sem desperdiçar contexto, chamadas de modelo ou quota.

## Politica de modelos

O GPT-5.6 Luna e reservado ao agente principal para coordenacao eficiente e tarefas locais.

Nao utilizes o modelo do agente principal em subagentes salvo pedido explicito do utilizador.

Distribui trabalho segundo a especializacao:

- coordenacao, decisoes globais e integracao: GPT-5.6 Luna;
- arquitetura e dominio complexo: GPT-5.6 Terra;
- implementacao Laravel/PHP: Kimi K2.7 Code via OpenCode Go;
- revisao de codigo e regressao: GLM-5.3-Flash via OpenCode Go;
- seguranca: GPT-5.6 Terra;
- continuidade e gestao de contexto: GLM-5.3-Flash via OpenCode Go;
- testes e debugging mecanico: modelos OpenCode Go quando apropriado.

Um subagente nunca deve alterar ou escalar autonomamente para o modelo do agente principal.

Quando um subagente encontrar uma decisao que exceda a sua funcao, deve devolver o bloqueio ao agente principal.

## Regra principal

Nao delegues por defeito.

## Database Safety

- Operacoes que possam apagar, truncar, substituir ou resetar dados sao estritamente proibidas sem autorizacao explicita do utilizador.
- Isto inclui `RefreshDatabase`, `migrate:fresh`, `db:wipe`, `truncate`, `drop`, resets de volumes e comandos equivalentes.
- Antes de qualquer teste ou comando que aceda a base de dados, confirma a ligacao efetiva e verifica que o ambiente esta isolado da base partilhada.
- Se houver qualquer duvida sobre a base ou o efeito do comando, nao executar e pedir confirmacao.
- Antes de qualquer operacao relacionada com base de dados, alerta explicitamente o utilizador sobre o ambiente, a ligacao e o possivel impacto.
- Nenhuma operacao que possa alterar uma base de dados de producao ou partilhada pode ser executada sem autorizacao explicita e especifica para essa operacao.
- Se a configuracao estiver em cache, se o ambiente nao estiver comprovadamente isolado ou se existir qualquer risco de atingir producao, para e nao executes o comando.
- Testes, seeders, migrations e comandos Artisan que acedam a base de dados devem ser tratados como operacoes sensiveis, mesmo quando parecem apenas de leitura ou de desenvolvimento.

Resolve diretamente tarefas pequenas, locais e bem definidas.

Usa subagentes apenas quando houver ganho claro em especializacao, independencia, reducao de risco ou reducao de trabalho do agente principal.

Nao uses subagentes apenas porque estao disponiveis.

## Limite de delegacao

Usa no maximo 1 subagente de cada vez por defeito.

So uses 2 subagentes em paralelo quando:

- as tarefas forem realmente independentes;
- nao precisarem de ler o mesmo contexto;
- a execucao paralela trouxer vantagem clara;
- nao houver duplicacao de investigacao.

Nunca uses mais de 2 subagentes simultaneamente.

Nao envies a mesma pergunta ou investigacao para varios agentes.

Nao uses varios agentes para obter "segunda opiniao" salvo pedido explicito do utilizador.

## Quando nao delegar

Nao uses subagentes para:

- alterar texto, labels ou mensagens;
- pequenas mudancas Blade;
- ajustes visuais simples;
- CRUDs triviais;
- alterar uma ou duas linhas;
- procurar uma unica referencia;
- executar um grep simples;
- ler um ou dois ficheiros;
- corrigir erros de sintaxe evidentes;
- executar um teste focado simples;
- tarefas que possam ser resolvidas diretamente com poucas tool calls.

# Architect

Usa `architect` apenas quando existir uma decisao estrutural real.

Exemplos:

- mudancas de schema;
- migrations relevantes;
- novas relacoes Eloquent;
- alteracoes que atravessem varios dominios;
- regras complexas de horarios;
- fluxos de aprovacao;
- autorizacao transversal;
- alteracao com risco de integridade de dados;
- decisoes arquiteturais com impacto futuro relevante.

Nao uses `architect` para validar implementacoes simples.

Depois de receber a recomendacao do architect:

- nao repitas a mesma investigacao;
- usa o resumo como contexto;
- decide;
- avanca.

O architect recomenda.

O agente principal decide.

# Laravel-Filament

Usa `laravel-filament` quando existir trabalho de implementacao significativo.

Exemplos:

- alteracoes em varios ficheiros;
- Resource + Model + Policy;
- implementacao de funcionalidade;
- refactor local necessario;
- criacao ou alteracao de testes associados;
- alteracoes Filament com logica nao trivial;
- implementacao Laravel que envolva multiplas camadas.

Nao delegues para `laravel-filament` se o agente principal conseguir fazer a alteracao diretamente com baixo custo.

Fornece sempre ao subagente:

- objetivo concreto;
- ficheiros ja identificados;
- restricoes;
- decisao arquitetural ja tomada;
- criterios de aceitacao.

Nao lhe pecas para "investigar tudo".

O subagente deve trabalhar dentro do escopo fornecido.

Se descobrir uma decisao arquitetural nao prevista, deve devolver a questao ao agente principal.

# Quality

Usa `quality` apenas depois de existir um diff relevante.

Nao uses automaticamente em todas as tarefas.

Usa quando:

- houver alteracao funcional;
- houver risco de regressao;
- forem alteradas regras de dominio;
- houver varios ficheiros envolvidos;
- forem alteradas queries;
- forem alteradas regras de autorizacao;
- houver refactor significativo;
- o utilizador pedir review.

Para alteracoes pequenas e localizadas, o agente principal pode rever diretamente.

O agente `quality` deve procurar principalmente:

- bugs funcionais;
- regressoes;
- comportamento inesperado;
- logica incorreta;
- edge cases;
- inconsistencias;
- testes em falta;
- alteracoes fora do escopo;
- problemas de manutencao com impacto concreto.

Nao deve sugerir refactors cosmeticos sem beneficio concreto.

# Security

Usa `security` apenas quando a alteracao tiver superficie de seguranca real.

Exemplos:

- autenticacao;
- autorizacao;
- Policies;
- roles/permissions;
- dados pessoais;
- exports;
- uploads;
- impersonation;
- alteracoes de ownership;
- endpoints;
- acesso horizontal a dados;
- secrets;
- configuracao de producao;
- validacao de input com impacto de seguranca;
- exposicao de informacao;
- sessoes;
- APIs;
- integracoes externas.

Nao uses `security` por defeito em CRUDs ou alteracoes visuais.

A analise de seguranca deve ter como referencia base:

- OWASP Top 10;
- OWASP API Security Top 10 quando aplicavel;
- principio do menor privilegio;
- validacao de autorizacao server-side;
- prevencao de acesso horizontal indevido;
- protecao de dados sensiveis;
- gestao segura de secrets;
- secure defaults.

O agente de seguranca deve priorizar riscos reais e demonstraveis.

Nao deve bloquear alteracoes por questoes meramente teoricas sem impacto pratico relevante.

# Ordem preferencial

Para uma tarefa complexa:

1. O agente principal compreende o pedido.
2. O agente principal faz uma pesquisa minima e direcionada.
3. Se necessario, chama `architect`.
4. O agente principal decide a solucao.
5. O agente principal implementa diretamente OU chama `laravel-filament`.
6. Executa testes focados.
7. Usa `quality` apenas se o risco justificar.
8. Usa `security` apenas se existir superficie de seguranca relevante.
9. Atualiza continuidade quando houver uma mudanca significativa.
10. O agente principal entrega a resposta final.

Nao uses toda a cadeia automaticamente.

Cada etapa deve justificar o seu custo.

# Politica de contexto

Evita carregar contexto desnecessario.

Nao leias o repositorio inteiro.

Nao leias ficheiros que nao sejam relevantes para a tarefa.

Prefere pesquisas direcionadas.

Comeca pela menor quantidade de contexto necessaria.

Expande apenas quando houver evidencia de que e necessario.

Quando um subagente devolver:

- ficheiros relevantes;
- linhas;
- conclusoes;
- recomendacoes;

nao voltes a repetir a mesma pesquisa sem motivo concreto.

Nao envies ficheiros completos para subagentes quando apenas uma pequena seccao for necessaria.

Nao copies grandes quantidades de contexto entre agentes sem necessidade.

# Politica de testes

Executa primeiro testes focados.

Nao executes automaticamente toda a suite.

Executa a suite completa apenas quando:

- a alteracao for transversal;
- houver risco significativo de regressao;
- os testes focados nao forem suficientes;
- o utilizador pedir explicitamente.

Nao executes `npm run build`, `composer audit` ou `npm audit` sem razao relacionada com a alteracao.

Quando possivel:

1. testa primeiro a funcionalidade alterada;
2. executa os testes diretamente relacionados;
3. expande apenas se surgirem sinais de regressao.

# Politica de eficiencia

Antes de cada delegacao, pergunta internamente:

1. Preciso mesmo de outro agente?
2. O agente principal consegue resolver isto com poucas tool calls?
3. Ja tenho informacao suficiente?
4. Outro agente vai repetir trabalho ja feito?
5. O custo adicional traz valor real?
6. Existe especializacao real que justifique a chamada?
7. O resultado do subagente sera diretamente utilizavel?

Se a resposta indicar pouco beneficio, nao delegues.

# Session Continuity Policy

O projeto usa continuidade persistente entre sessoes.

O diretorio de continuidade e:

`.opencode/context/`

Estrutura:

- `.opencode/context/current-state.md`
- `.opencode/context/decisions.md`
- `.opencode/context/session-log.md`

A continuidade existe para permitir retomar trabalho apos:

- fecho do terminal;
- crash;
- interrupcao inesperada;
- perda de contexto;
- nova sessao OpenCode;
- saida acidental.

Principio fundamental:

`Do not persist conversation. Persist project state.`

## Fonte de verdade

O repositorio representa a realidade da implementacao.

Os ficheiros de continuidade representam:

- intencao;
- estado conhecido;
- decisoes;
- progresso;
- working memory.

Nunca confies cegamente num checkpoint.

Quando houver conflito:

`repository state > stale continuity state`

Investiga a diferenca antes de modificar codigo.

## Session start

No inicio de uma nova sessao, antes de trabalho substancial:

1. verifica se `.opencode/context/current-state.md` existe;
2. le o ficheiro quando existir;
3. inspeciona o estado atual do repositorio;
4. compara o estado persistido com o estado real;
5. identifica possiveis alteracoes posteriores ao ultimo checkpoint;
6. retoma a partir do estado real do projeto.

Nao carregues automaticamente todo o historico.

`decisions.md` e `session-log.md` devem ser lidos apenas quando forem necessarios para compreender:

- uma decisao;
- um conflito;
- uma tentativa anterior;
- uma mudanca arquitetural;
- contexto historico relevante.

# Resume Protocol

Quando estiveres a restaurar uma sessao interrompida:

1. le `.opencode/context/current-state.md`;
2. executa ou inspeciona `git status`;
3. inspeciona `git diff --stat`;
4. inspeciona `git diff` quando relevante;
5. verifica commits recentes quando relevante;
6. verifica os ficheiros mencionados em `current-state.md`;
7. compara a intencao registada com o estado real;
8. identifica alteracoes incompletas;
9. determina a acao seguinte mais segura.

Usa o principio:

`CURRENT INTENT + CURRENT REPOSITORY STATE = SAFE NEXT ACTION`

Nunca repitas automaticamente a ultima acao registada.

A acao pode ja ter sido:

- executada;
- parcialmente executada;
- concluida antes do terminal fechar;
- substituida por outra implementacao.

Verifica primeiro.

# During Work

Nao atualizes os ficheiros de continuidade apos cada prompt.

Um checkpoint deve registar o resultado de trabalho significativo, nao cada interacao individual.

Atualiza o checkpoint quando ocorrer uma mudanca relevante, incluindo:

- conclusao de uma tarefa;
- conclusao de uma subtarefa significativa;
- identificacao da causa raiz de um bug relevante;
- decisao arquitetural;
- alteracao da estrategia de implementacao;
- criacao de ficheiros importantes;
- alteracao substancial de ficheiros importantes;
- conclusao de testes relevantes;
- descoberta ou resolucao de um blocker;
- mudanca para outra tarefa importante;
- descoberta que invalide uma hipotese anterior.

Para alteracoes triviais, nao cries checkpoint.

# Before Session Termination

Quando o utilizador indicar que o trabalho esta a terminar, ou quando existir um ponto natural de paragem:

atualiza:

`.opencode/context/current-state.md`

O checkpoint deve permitir que uma nova sessao continue sem precisar da conversa anterior.

# Context Restoration

Se o utilizador disser algo semelhante a:

- continue;
- continua;
- resume;
- retoma;
- onde ficamos;
- onde estavamos;
- pick up where we left off;
- terminal closed;
- fechei o terminal;
- session crashed;
- perdi a sessao;
- sai sem querer;
- continua de onde estavamos;

restaura primeiro o contexto do projeto.

Nao perguntes ao utilizador o que estava a ser feito enquanto houver informacao suficiente no estado persistente e no repositorio.

# Continuity Agent

O `continuity` e um agente de infraestrutura de contexto.

Nao e um agente de implementacao.

Nao deve ser chamado continuamente.

O agente principal pode atualizar checkpoints simples diretamente.

Usa `continuity` quando:

- a sessao contem muitas alteracoes;
- varios ficheiros precisam de ser reconciliados;
- varias decisoes precisam de ser consolidadas;
- o contexto ficou demasiado grande;
- uma sessao esta a ser recuperada apos interrupcao;
- existe possivel divergencia entre estado persistido e repositorio;
- e necessario compactar working memory;
- e necessario preparar um checkpoint complexo.

Nao o uses apenas para adicionar uma linha simples ao estado.

# Current State Discipline

`current-state.md` deve representar a verdade atual conhecida.

Nao acumules informacao obsoleta ou contraditoria.

Quando algo mudar, substitui a informacao antiga.

O historico pertence em:

- `decisions.md`, para decisoes relevantes;
- `session-log.md`, para historico operacional resumido.

Mantem `current-state.md` curto e atual.

Objetivo:

- normalmente abaixo de 1000 palavras;
- maximo aproximado de 1500 palavras.

Quando crescer demasiado:

- remove informacao obsoleta;
- compacta explicacoes;
- move historico para os ficheiros apropriados.

# Working Memory

Quando util, `current-state.md` pode incluir:

- Known Good;
- Known Bad;
- Do Not Retry;
- Current Hypothesis.

Usa `Do Not Retry` para abordagens ja testadas que nao resolveram o problema.

Nao repitas uma abordagem falhada salvo se existir nova evidencia que justifique repetir o teste.

# Seguranca da continuidade

Nunca persistir secrets nos ficheiros de continuidade.

Nunca escrever:

- passwords;
- tokens;
- API keys;
- private keys;
- environment secrets;
- credentials;
- session cookies;
- authentication headers;
- connection strings com passwords.

Regista apenas a existencia ou necessidade da configuracao.

# Alteracoes fora do escopo

Nao aproveites uma tarefa para:

- refatorar codigo nao relacionado;
- renomear ficheiros sem necessidade;
- alterar padroes arquiteturais;
- atualizar dependencias;
- reorganizar modulos;
- corrigir problemas nao relacionados;
- melhorar estilo de codigo fora do diff necessario.

Se encontrares um problema importante fora do escopo:

- informa o utilizador;
- nao o resolvas automaticamente salvo risco critico ou instrucao explicita.

# Conclusao antecipada

Quando os criterios de aceitacao estiverem cumpridos:

- para de investigar;
- para de refatorar;
- nao procures melhorias adicionais fora do escopo;
- nao abras novas linhas de investigacao sem necessidade;
- executa apenas validacoes proporcionais ao risco;
- entrega o resultado.

Nao expandas o pedido sem necessidade.

# Principio final de orquestracao

O agente principal mantem responsabilidade final por:

- compreender o pedido;
- selecionar contexto;
- decidir se delega;
- integrar resultados;
- decidir arquitetura;
- controlar escopo;
- validar o resultado;
- manter continuidade;
- entregar a resposta final.

Subagentes sao ferramentas especializadas.

Nao sao coordenadores autonomos.

Usa o menor numero de agentes, chamadas, ficheiros e testes necessarios para produzir uma solucao correta, segura e sustentavel.
