<!--
Emenda formal da constituição.
- Tipo: MAJOR
- Versão anterior: 2.0.0
- Nova versão: 3.0.0
- Data: 2026-10-20
- Motivo: o modelo derivado recalculava o consumo de placas históricas quando peça.filamento_g era editada, alterando o estoque do passado. O novo modelo persiste o saldo como cache materializado, congela o consumo na placa no momento da conclusão e mantém um ledger append-only para auditoria e reconciliação.
- Princípios afetados: I (substituído), II (endurecido), III (reforçado), novos princípios: LEDGER DE MOVIMENTAÇÕES e RECONCILIAÇÃO DE SALDO.
- Princípios preservados: III, IV, V, VI, VII, VIII, IX da constituição anterior.
-->

# Constituição do Sistema de Gestão de Produção 3D

## Core Principles

### I. SALDO PERSISTIDO COMO CACHE MATERIALIZADO + SNAPSHOT NA PLACA
A tabela de filamentos possui coluna `saldo` (gramas), que é a leitura rápida do estoque disponível. O saldo NÃO é fonte primária: é cache materializado do ledger de movimentações (ver novo princípio LEDGER). A tabela de placas possui coluna `consumo_filamento_g`, que CONGELA o consumo calculado (soma de `peça.filamento_g × quantidade`) no exato momento em que a placa transiciona para CONCLUÍDO.

Regras associadas:
- Editar `peça.filamento_g` depois que uma placa foi concluída NÃO altera o consumo histórico dessa placa: o valor está congelado em `placa.consumo_filamento_g`. Isso preserva a integridade do estoque no passado.
- O saldo é atualizado em dois momentos transacionais:
  (a) entrada de filamento → incrementa saldo e grava movimentação de ENTRADA;
  (b) conclusão de placa → decrementa saldo por `placa.consumo_filamento_g` (recém-congelado) e grava movimentação de CONSUMO.
- O saldo nunca pode ficar negativo. A conclusão valida saldo >= consumo antes de decrementar; se insuficiente, bloqueia com mensagem clara e zero efeito colateral.
- Atualizações de saldo usam controle de concorrência (lock de linha ou decremento atômico condicional). Conclusões simultâneas não podem produzir saldo incorreto.
- Edição manual de saldo é proibida na aplicação. Correções só via o mecanismo de reconciliação (novo princípio RECONCILIAÇÃO).

### II. Idempotência na Conclusão de Placa
A transição de uma placa para CONCLUÍDO deve gerar os itens de estoque exatamente uma vez. Repetir a operação por duplo clique, retry de rede, reprocessamento ou concorrência não pode duplicar itens, nem decrementar saldo duas vezes, nem gravar duas movimentações. A idempotência protege o estado persistido, o snapshot, o saldo e o ledger.

Regras adicionais:
- A conclusão de placa é uma transação única que executa, atomicamente: (i) congela `placa.consumo_filamento_g`; (ii) cria os itens; (iii) decrementa `filamento.saldo`; (iv) grava movimentação CONSUMO no ledger; (v) muda status para CONCLUÍDO. Se qualquer etapa falhar, todas revertem (rollback total).
- Repetir a transição para CONCLUÍDO (duplo clique, retry, reprocessamento) NÃO pode duplicar itens, NEM decrementar saldo duas vezes, NEM gravar duas movimentações. A idempotência protege o estado persistido, o snapshot, o saldo e o ledger.
- Implementação recomendada: transição de estado validada no banco (`UPDATE ... WHERE status = 'IMPRIMINDO' RETURNING 0 linhas se já concluída`) ou chave de idempotência (`placa_id + tipo_conclusao`).

### III. Ciclo de Vida Explícito da Placa
O status da placa segue a sequência criado → fila → imprimindo → concluído. Transições fora dessa sequência são bloqueadas. Toda mudança de status deve ser registrada com data e hora e referência à placa, mantendo histórico auditável para operação e diagnóstico. A ordenação da fila é um campo persistido e determinístico, não apenas visual, para que o drag-and-drop sobreviva a recarregamentos e seja reproduzível em qualquer ambiente.

Além disso:
- Toda alteração de saldo DEVE gerar registro no ledger de movimentações (ver novo princípio LEDGER). O ledger é a fonte de auditoria; o saldo é apenas cache. Sem movimentação, a alteração de saldo é inválida.
- A reabertura de uma placa CONCLUÍDO (se permitida pelo spec) DEVE gravar movimentação de ESTORNO no ledger, incrementando o saldo pelo valor congelado em `placa.consumo_filamento_g`, e DEVE remover os itens gerados — tudo em transação única e idempotente. Se a reabertura não for permitida, isso deve ser dito explicitamente no spec.

### IV. Rastreabilidade de Composição
Todo produto é decomposto em peças com quantidades. Todo item de estoque referencia uma peça e uma placa. Todo consumo de filamento é rastreável à placa e às peças que a compõem. Não existe valor de custo, peso ou tempo "estimado" no código: todos derivam de dados cadastrados, como peso da peça, tempo de produção, valor do filamento e valor de venda do produto. O sistema deve permitir reconstruir a composição e o custo a partir do histórico de produção.

### V. Identificadores e Chaves Compostas
O código do filamento segue o padrão material-marca-cor-incremental, exemplo: PLA-MULTFILA-ROSA-0001, e é chave primária composta. O código da peça é informado pelo usuário. O código do produto é informado pelo usuário. A geração do incremental do filamento deve ser atômica e livre de colisão em ambiente concorrente. Não se substitui chave natural por surrogate key sem justificativa registrada e aprovada conforme esta constituição.

### VI. Autenticação e Sessão
Autenticação padrão com login e senha, com suporte a "manter logado" por meio de cookie persistente. Sessões e tokens seguem os padrões do Laravel; não é permitido reinventar autenticação. Nenhuma tela do sistema é acessível sem autenticação. Senhas são sempre armazenadas com hash forte do framework e nunca em texto puro, reversível ou em formato inseguro.

### VII. Simplicidade para o Pequeno Empreendedor
A interface prioriza clareza e velocidade. Fluxos críticos — registrar placa, mudar status, consultar estoque e adicionar filamento — devem ser executáveis em poucos cliques. O dashboard deve responder imediatamente às perguntas: quanto filamento tenho, o que está na fila e o que posso montar ou vender hoje. Recursos visuais como drag-and-drop e collapse existem para reduzir atrito operacional, não para enfeitar a interface.

### VIII. Testes como Contrato
Regras de negócio — cálculo de filamento disponível, idempotência da conclusão de placa, transições de status, geração de código de filamento e autorização — exigem testes automatizados antes da implementação. Nenhuma regra de negócio é considerada concluída sem teste que a valide, incluindo testes de concorrência para a conclusão de placa e para o incremental de filamento. O ciclo TDD é obrigatório e não negociável.

### IX. Containerização e Ambiente
Todo o sistema roda em Docker: aplicação Laravel, MySQL e serviços auxiliares descritos em docker-compose. O ambiente de desenvolvimento é reproduzível a partir do repositório e não depende do "funciona na minha máquina". Migrações do banco são versionadas e reversíveis. Segredos e credenciais ficam em variáveis de ambiente, nunca no código-fonte ou em arquivos versionados.

### B. LEDGER APPEND-ONLY DE FILAMENTO
Existe uma tabela `movimentacoes_filamento` com, no mínimo:
- `id`
- `filamento_codigo` (FK)
- `tipo` (`ENTRADA | CONSUMO | ESTORNO | AJUSTE_RECONCILIACAO`)
- `quantidade_g` (positiva em entrada/estorno, negativa em consumo)
- `saldo_anterior_g`
- `saldo_novo_g`
- `origem` (referência: `entrada_id`, `placa_id`, ou `null` em ajuste de reconciliação)
- `data_hora`
- `usuario_id` (quando aplicável)

Regras:
- O ledger é append-only: nunca se apaga nem se edita uma movimentação. Correções são feitas por movimentação compensatória (ESTORNO ou AJUSTE).
- O saldo de filamento é sempre reconciliável a partir do ledger: `saldo_esperado = SUM(movimentacoes.quantidade_g)`. O saldo persistido deve bater com esse valor, exceto durante transações em andamento.
- Toda operação que altere saldo DEVE gravar exatamente uma movimentação na mesma transação.

### C. RECONCILIAÇÃO OBRIGATÓRIA
- Deve existir um comando/rotina de reconciliação que recalcula o saldo esperado a partir do ledger (`SUM(quantidade_g) por filamento`) e compara com o saldo persistido.
- Divergências são registradas em log e, mediante confirmação explícita, corrigidas via movimentação `AJUSTE_RECONCILIACAO` — nunca por `UPDATE` direto no saldo.
- O comando é executável manualmente e deve ser documentado. A existência do mecanismo é pré-requisito para qualquer release.
- O snapshot `placa.consumo_filamento_g` é a fonte de verdade histórica do consumo; o ledger é a fonte de verdade da variação do saldo; a reconciliação cruza os dois e sinaliza inconsistências.

## Stack Obrigatória

A arquitetura deste projeto deve seguir estritamente a stack definida abaixo e qualquer decisão que fuja dela viola esta constituição.

- Backend: PHP com Laravel.
- Frontend: Inertia.js + Vue.
- Banco de dados: MySQL.
- Ambiente: Docker para desenvolvimento e execução em container.

Nenhuma alternativa técnica pode substituir esses componentes sem emenda formal da constituição e justificativa documentada de conflito arquitetural.

## Portões de Qualidade

### Regras obrigatórias de qualidade

- Toda regra de negócio precisa de teste automatizado antes da implementação.
- Todo cálculo de disponibilidade de filamento e de estoque deve ser testado contra dados reais de produção.
- Toda conclusão de placa precisa ter teste de idempotência e concorrência.
- Toda transição de status precisa validar sequência, bloqueio de status inválidos e histórico persistido.
- Toda geração de código de filamento precisa validar concorrência, unicidade e composição do padrão material-marca-cor-incremental.
- Toda mudança de schema precisa incluir migração versionada e reversível.
- Nenhuma operação crítica pode depender de cálculo manual, dado derivado não persistido ou cache que substitua a fonte de verdade do sistema.

### Fluxo de desenvolvimento

1. Definir a regra de negócio e os seus invariantes.
2. Escrever o teste automatizado que descreve o comportamento esperado.
3. Implementar a menor mudança necessária para satisfazer o teste.
4. Validar impacto em filamento, peças, placas, histórico e autenticação.
5. Executar testes relevantes de unidade, integração e concorrência antes de entrega.
6. Registrar qualquer conflito com a constituição e justificar a decisão antes de aprovar a solução.

### Critérios de revisão de código

A revisão deve verificar, no mínimo:

- Uso estrito da stack obrigatória: Laravel, Inertia.js, Vue, MySQL e Docker.
- Persistência do saldo como cache materializado e existência de ledger append-only.
- Idempotência da operação de conclusão de placa e ausência de duplicação de itens, itens de saldo ou movimentações.
- Sequência de status da placa, histórico auditável e ordenação persistida da fila.
- Rastreabilidade da composição do produto e do material consumido.
- Geração atomica de códigos de filamento com chaves compostas e sem colisões concorrentes.
- Autenticação padronizada do Laravel com senha em hash e acesso protegido por autenticação.
- Cobertura adequada de testes para regras de negócio e concorrência.
- Migrações versionadas, reversíveis e compatíveis com o ambiente Docker.

## Governance

Esta constituição substitui integralmente qualquer versão anterior do projeto. Ela prevalece sobre preferências técnicas pontuais, convenções locais e decisões de arquitetura que entrem em conflito com seus princípios. Toda decisão de arquitetura que conflite com um princípio deve ser registrada e justificada, ou o princípio deve ser emendado formalmente.

A governança do projeto segue estas regras:

- A constituição prevalece sobre qualquer stack, padrão ou convenção técnica que discordem dos princípios definidos.
- Emendas exigem versionamento semântico: MAJOR para substituição ou remoção de princípios; MINOR para inclusão de novos princípios ou expansão material de orientação; PATCH para ajustes, correções e refinamentos não semânticos.
- A substituição total desta constituição conta como MAJOR.
- Toda mudança deve ser registrada com motivo, data e alcance da alteração.
- A revisão de conformidade deve confirmar que a implementação permanece consistente com a stack obrigatória, os princípios do domínio e os testes que validam as regras de negócio.

**Version**: 3.0.0 | **Ratified**: 2026-10-20 | **Last Amended**: 2026-10-20

## Confirmação da Emenda

(a) Nova versão registrada: 3.0.0.
(b) Princípios alterados: I, II e III.
(c) Princípios preservados: IV, V, VI, VII, VIII e IX.
(d) Dois novos princípios adicionados: B. LEDGER APPEND-ONLY DE FILAMENTO e C. RECONCILIAÇÃO OBRIGATÓRIA.
(e) A proibição de editar `peça.filamento_g` para efeito retroativo está registrada no Princípio I, que congela `placa.consumo_filamento_g` no momento da conclusão e impede que mudanças posteriores alterem o consumo histórico da placa.
