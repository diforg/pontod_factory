# Feature Specification: Gestão de Produção 3D

**Feature Branch**: `001-gestao-producao-3d`

**Created**: 2026-10-20

**Status**: Draft

**Input**: User description: "Sistema web de gestão de produção para pequeno empreendedor que fabrica produtos com impressora 3D. Ciclo operacional: cadastro de filamentos → cadastro de peças → cadastro de produtos → criação de placas de impressão → impressão → transição de status até CONCLUÍDO → geração automática de itens em estoque → decremento do saldo de filamento."

## Acknowledge Previous Model is Discarded

Esta especificação substitui integralmente o modelo derivado anterior, que recalculava estoque sob demanda a partir de placas históricas. O modelo adotado neste documento é o modelo do meio fixado pela constituição: saldo persistido como cache materializado, snapshot congelado em `placa.consumo_filamento_g`, ledger append-only de movimentações e reconciliação obrigatória via `AJUSTE_RECONCILIACAO`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Autenticação e acesso seguro (Priority: P1)

Como pequeno empreendedor, quero fazer login com e-mail e senha e manter-me autenticado no navegador, para acessar o sistema rapidamente sem repetir a autenticação em cada sessão.

**Why this priority**: O sistema é operado em fluxo de produção e qualquer acesso não autenticado compromete o controle de produção, estoque e dados financeiros.

**Independent Test**: Um usuário com credenciais válidas consegue entrar, manter sessão persistente e acessar as telas protegidas; um usuário sem autenticação é redirecionado ao login.

**Acceptance Scenarios**:

1. **Given** um usuário cadastrado com e-mail e senha válidos, **When** ele informa as credenciais e marca "manter logado", **Then** o sistema autentica a sessão e cria um cookie persistente válido por período estendido.
2. **Given** um usuário autenticado, **When** ele solicita qualquer rota protegida, **Then** o sistema permite o acesso sem exigir nova autenticação até o término da sessão ou logout.
3. **Given** um usuário não autenticado, **When** ele tenta acessar qualquer rota do sistema, **Then** o sistema redireciona para login e bloqueia o acesso.
4. **Given** um usuário realiza logout, **When** a sessão termina, **Then** o sistema invalida a sessão e remove o cookie persistente.
5. **Given** uma senha foi armazenada, **When** o sistema persiste a credencial, **Then** ela é guardada com hash forte do Laravel e nunca em texto puro.

---

### User Story 2 - Ciclo completo de produção e estoque (Priority: P1)

Como pequeno empreendedor, quero cadastrar filamentos, peças, produtos e placas, concluir a impressão e gerar itens em estoque de forma segura, para controlar produção e disponibilidade sem erro operacional.

**Why this priority**: Este é o núcleo do produto: existe valor real apenas quando a produção, o estoque e as movimentações de filamento são rastreáveis e consistentes.

**Independent Test**: Cadastrando um filamento, criando peças e uma placa, concluindo a placa e validando itens e saldo, o usuário consegue verificar o ciclo completo sem intervenção manual.

> Observação operacional: "adicionar filamento" neste sistema significa criar um NOVO rolo. O saldo de um filamento existente só muda por `CONSUMO`, `ESTORNO` ou `AJUSTE_RECONCILIACAO`; não existe fluxo de incremento manual sobre um rolo já existente.

**Acceptance Scenarios**:

1. **Given** um rolo de filamento novo com peso e valor informados, **When** o cadastro é concluído, **Then** o sistema cria o registro de filamento e registra automaticamente uma movimentação de ENTRADA no ledger com o peso informado, mantendo o saldo inicial consistente com a reconciliação.
2. **Given** um filamento ativo com saldo inicial, **When** o usuário cadastra uma peça com `filamento_g` e cria uma placa com essa peça em quantidade, **Then** a placa é criada com aviso visual se o consumo estimado estiver acima do saldo disponível, mas continua permitida até a conclusão, quando a validação de saldo se torna obrigatória.
3. **Given** uma placa no status IMPRIMINDO, **When** o usuário conclui a placa, **Then** o sistema congela `placa.consumo_filamento_g`, cria itens em estoque, decrementa o saldo do filamento e grava uma movimentação de CONSUMO no ledger dentro da mesma transação.
4. **Given** uma placa concluída, **When** a operação de conclusão é repetida por retry ou clique duplicado, **Then** o sistema não cria itens duplicados, não decrementa saldo duas vezes e não grava duas movimentações.
5. **Given** uma peça com `filamento_g` alterado depois da conclusão, **When** o usuário consulta a placa histórica, **Then** o consumo daquela placa permanece congelado no valor original registrado em `placa.consumo_filamento_g`.
6. **Given** o saldo do filamento é insuficiente para a placa, **When** o usuário tenta concluir a placa, **Then** o sistema bloqueia a ação com mensagem clara e evita qualquer efeito colateral.
7. **Given** uma placa concluída e a reabertura for solicitada, **When** a operação é validada, **Then** o sistema executa estorno transacional com `ESTORNO` no ledger, remove os itens gerados e volta o status para IMPRIMINDO sem duplicar registros.

---

### User Story 3 - Dashboard e operação visual (Priority: P2)

Como pequeno empreendedor, quero visualizar rapidamente os filamentos ativos com saldo, as placas pendentes e os produtos expansíveis com estoque, para decidir o que produzir, o que está em fila e o que posso vender.

**Why this priority**: O dashboard reduz atrito operacional, permite agilidade e ajuda na tomada de decisão sem sair do fluxo principal.

**Independent Test**: Ao abrir o dashboard autenticado, o usuário vê os blocos de filamentos, placas e produtos com informação útil e links para as telas de detalhe.

**Acceptance Scenarios**:

1. **Given** o usuário acessa o dashboard após login, **When** a página carrega, **Then** os filamentos ativos exibem saldo, as placas não concluídas aparecem em ordem persistida e os produtos exibem peças com quantidade disponível em estoque.
2. **Given** uma placa não concluída, **When** o usuário arrasta a placa em outra posição da fila, **Then** a nova ordenação é persistida e sobrevive a reload.
3. **Given** um produto com múltiplas peças, **When** o usuário expande o produto no dashboard, **Then** a interface mostra as peças e a quantidade de itens disponíveis de cada uma.

---

### User Story 4 - Reconciliação e auditoria (Priority: P2)

Como administrador do sistema, quero reconciliar o saldo persistido com o ledger e corrigir divergências de forma controlada, para manter a integridade do estoque e a rastreabilidade histórica.

**Why this priority**: Sem reconciliação, um desvio pontual pode quebrar a confiabilidade do estoque e comprometer as decisões do empreendedor.

**Independent Test**: Simulando divergência entre `filamento.saldo` e `SUM(movimentacoes.quantidade_g)`, o sistema lista a inconsistência e, em modo apply, corrige via `AJUSTE_RECONCILIACAO` sem alterar o saldo diretamente.

**Acceptance Scenarios**:

1. **Given** um filamento com divergência entre saldo persistido e saldo esperado no ledger, **When** o comando de reconciliação executa em modo dry-run, **Then** o sistema reporta a diferença sem alterar o registro.
2. **Given** o mesmo cenário com confirmação explícita, **When** o comando executa em modo apply, **Then** o sistema grava uma movimentação de `AJUSTE_RECONCILIACAO` no ledger e atualiza o saldo por mecanismo controlado.
3. **Given** qualquer movimentação de saldo, **When** ela é registrada, **Then** o ledger guarda `saldo_anterior_g`, `saldo_novo_g`, origem, data/hora e usuário, sem edição ou exclusão.

---

### User Story 5 - Consulta e rastro do material (Priority: P3)

Como pequeno empreendedor, quero consultar o histórico de movimentações do filamento e associar cada mudança a uma origem, para entender o que ocorreu com o estoque.

**Why this priority**: Esse recurso aumenta confiança operacional e reduz custos de suporte quando o saldo ou a produção parece inconsistente.

**Independent Test**: O usuário visualiza o ledger de um filamento com filtros por tipo e período, e cada movimentação aponta para entrada ou placa.

**Acceptance Scenarios**:

1. **Given** um filamento com diversas entradas e consumos, **When** o usuário abre a tela de movimentações, **Then** o sistema lista registros ordenados por data e hora decrescente.
2. **Given** uma movimentação de consumo originada de uma placa, **When** o usuário clica na origem, **Then** o sistema navega ou resolve o item para a placa correta.
3. **Given** uma movimentação de ajuste de reconciliação, **When** o sistema registra a correção, **Then** ele mantém o histórico inalterado e adiciona somente a compensação necessária.

---

### Edge Cases

- O que acontece se uma placa tentar mudar de CRIADO para CONCLUIDO diretamente? A transição é bloqueada pela sequência definida.
- Como o sistema lida com duas conclusões simultâneas da mesma placa? A operação deve usar idempotência por status e/ou chave de idempotência para garantir que apenas um processo faça a transação.
- O que acontece se o saldo persistido é menor que o consumo congelado de uma placa? A conclusão é bloqueada com mensagem clara e zero efeito colateral.
- O que acontece se a peça for editada após a conclusão? O sistema não altera `placa.consumo_filamento_g`, preservando o histórico da placa.
- O que acontece se o saldo persistido divergir do ledger? A reconciliação identifica o problema e exige correção via `AJUSTE_RECONCILIACAO`, nunca por `UPDATE` direto.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST authenticate users via standard Laravel login and password flow with a persistent "remember me" cookie option.
- **FR-002**: The system MUST block access to any protected route unless the user is authenticated.
- **FR-003**: The system MUST allow logout to terminate the current authenticated session and invalidate the persistent login cookie.
- **FR-004**: The system MUST hash passwords using the framework standard and never store them in plaintext or reversible form.
- **FR-005**: The system MUST model each filamento as a rolo individual identified by `MATERIAL-MARCA-COR-NNNN`; the balance per material type is derived from the active rolos, and the ledger references `filamento_codigo` as the originating rolo.
- **FR-006**: The system MUST generate filament codes in the format `MATERIAL-MARCA-COR-NNNN`, using an atomic incremental generator that avoids collisions in concurrent environments.
- **FR-007**: The system MUST keep the filament code immutable after creation.
- **FR-008**: The system MUST persist a `saldo` field on `filamento` as a materialized cache and prohibit manual editing through the application.
- **FR-009**: The system MUST record every balance-changing operation in a ledger of `movimentacoes_filamento` with `saldo_anterior_g`, `saldo_novo_g`, `tipo`, `quantidade_g`, `origem`, timestamp and user reference.
- **FR-010**: The system MUST allow creation of a new filament roll as a ledger `ENTRADA` transaction that sets `filamento.saldo = peso` atomically with the ledger record; the initial creation of a filamento must create the corresponding `ENTRADA` transaction automatically. The system MUST NOT support an "incrementar saldo" workflow for an existing filament; existing filaments only change balance through `CONSUMO`, `ESTORNO` or `AJUSTE_RECONCILIACAO`.
- **FR-011**: The system MUST allow creation, listing, activation, deactivation and archival-safe deletion policies for pieces.
- **FR-012**: The system MUST allow a piece to be associated with one or more final products and store `filamento_g`, `tempo_producao_min`, `tamanho` and description.
- **FR-013**: The system MUST preserve historical plate consumption even when a piece's `filamento_g` is edited after the plate is concluded by freezing the value in `placa.consumo_filamento_g`.
- **FR-014**: The system MUST allow creation, listing and editing of products with code, name, description, size, version and sale value.
- **FR-015**: The system MUST represent the product-to-piece relationship as a structured association, not as ad hoc text or duplicated logic.
- **FR-016**: The system MUST allow creation of print plates with a selected filament and a list of pieces with quantities, showing a warning if projected consumption exceeds saldo but still allowing creation until the formal conclusion step.
- **FR-017**: The system MUST restrict plate editing to status CRIADO and prevent edits for other statuses.
- **FR-018**: The system MUST enforce the explicit plate lifecycle: `CRIADO -> FILA -> IMPRIMINDO -> CONCLUIDO` and reject transitions outside the sequence.
- **FR-019**: The system MUST record each status transition with timestamp and previous status.
- **FR-020**: The system MUST persist a deterministic `ordenacao` field for plate queue ordering and survive reloads and session restarts.
- **FR-021**: The system MUST perform plate conclusion as one atomic transaction: freeze consumption, validate balance, create stock items, decrement saldo, write ledger CONSUMO and set status to CONCLUIDO.
- **FR-022**: The system MUST reject duplicate completion attempts without duplicating items, decrementing saldo twice or writing duplicate ledger entries.
- **FR-023**: The system MUST block plate conclusion when `filamento.saldo < placa.consumo_filamento_g` and keep the database unchanged.
- **FR-024**: The system MUST create one stock item per unit of each piece included in the concluded plate, linking each item to the piece and the plate.
- **FR-025**: The system MUST maintain the `filamento.saldo` cache as the result of the ledger plus controlled adjustments, never as a manual free-form field edited by the application.
- **FR-026**: The system MUST expose the dashboard with three blocks: active filaments with saldo, unfinished plates in queue and products with collapsible pieces and available stock.
- **FR-027**: The system MUST support drag-and-drop reordering of unfinished plates with persisted ordering.
- **FR-028**: The system MUST support collapsible product views showing the pieces and the available quantity of each piece in stock.
- **FR-029**: The system MUST provide a reconciliation command that recalculates expected saldo as `SUM(movimentacoes.quantidade_g)` for each filament and compares it with the stored saldo.
- **FR-030**: The system MUST reconcile divergences using `AJUSTE_RECONCILIACAO` entries, never by direct `UPDATE` on the saldo field.
- **FR-031**: The system MUST allow querying ledger movements by filament with filters by type and period.
- **FR-032**: The system MUST expose the ledger as append-only and prohibit editing or deletion of historical movements.
- **FR-033**: The system MUST display an alert when persisted saldo differs from the reconciled expected value.
- **FR-034**: The system MUST support measurements in grams, minutes and currency values consistent with the domain model and raw production data.
- **FR-035**: The system MUST maintain full traceability from item → piece → plate → filament → ledger movement.
- **FR-036**: The system MUST implement the application in Laravel + Inertia + Vue + MySQL and run with Docker, as required by the constitution.
- **FR-037**: The system MUST support re-opening a concluded plate only through a transacional estorno flow that writes `ESTORNO` movement to the ledger, restores the saldo from the frozen snapshot and removes the generated stock items in the same transaction.

### Key Entities *(include if feature involves data)*

- **Filamento**: Represents the raw spool material used in prints. Key attributes: `codigo`, `material`, `marca`, `cor`, `peso`, `valor`, `ativo`, `saldo`, `created_at`, `updated_at`. Each filamento row represents one rolo and is treated as the origin of every `ENTRADA` and `CONSUMO` ledger movement.
- **MovimentacaoFilamento**: Append-only ledger entry for any change in filament balance. Attributes: `id`, `filamento_codigo`, `tipo`, `quantidade_g`, `saldo_anterior_g`, `saldo_novo_g`, `origem_tipo`, `origem_id`, `data_hora`, `usuario_id`.
- **Peca**: Represents one printable part. Attributes: `id`, `codigo`, `descricao`, `tamanho`, `filamento_g`, `tempo_producao_min`, `ativo`.
- **Produto**: Represents the final sellable item. Attributes: `id`, `codigo`, `nome`, `descricao`, `tamanho`, `versao`, `valor_venda`.
- **Placa**: Represents one print batch/queue item. Attributes: `id`, `data_hora`, `filamento_codigo`, `status`, `ordenacao`, `consumo_filamento_g`, `data_conclusao`.
- **PlacaPeca**: Association that holds quantity of each piece in a plate and, where relevant, a frozen consumption per piece. Attributes: `placa_id`, `peca_id`, `quantidade`, `consumo_filamento_g`.
- **ProdutoPeca**: Association between a product and its constituent pieces. Attributes: `produto_id`, `peca_id`, `quantidade`.
- **ItemEstoque**: Produced stock item created after a plate is concluded. Attributes: `id`, `peca_id`, `placa_id`, `data_criacao`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can log in and access the protected dashboard within 2 minutes without technical assistance.
- **SC-002**: A production run can be created, status-updated and concluded in a single user flow without manual recalculation of filament or stock.
- **SC-003**: A concluded plate creates all expected stock items exactly once and produces one ledger movement for the consumption event.
- **SC-004**: A system operator can detect and correct a divergence between stored saldo and ledger sum within one reconciliation run.
- **SC-005**: 100% of business-critical rules related to stock, plate status and ledger transitions are covered by automated tests before release.
- **SC-006**: The platform remains runnable in Docker-based local development and can be recreated from the repository without manual environment hacks.

## Clarifications Applied

- Clarified date/time: 2026-10-20T00:00:00Z
- Constitution version consulted: 3.0.0 (modelo do meio: cache materializado + snapshot + ledger + reconciliação)
- Original "Clarifications Needed" section removed and resolved by the decisions below.

### Decisions

- **A1 — Option A**: Cada rolo é uma linha nova em `filamentos`. Justificativa: mantém a identidade do rolo e o ledger referencia a origem por `filamento_codigo`, alinhando-se ao modelo do meio e ao código composto do filamento.
- **A2 — Option A**: `part_product.quantity` existe com `INT NOT NULL DEFAULT 1` e `CHECK (quantity > 0)`. Justificativa: um produto pode incluir N unidades da mesma peça (ex.: 2× Domo Inferior A) sem perder rastreabilidade.
- **A3 — Option B**: Alertar no cadastro e bloquear apenas na conclusão. Justificativa: permite planejamento do empreendedor sem permitir saldo negativo e mantém o bloqueio duro na conclusão exigido pela constituição.
- **A4 — Option B**: Placa concluída pode ser reaberta com estorno transacional via `ESTORNO` no ledger, usando o snapshot `placa.consumo_filamento_g`. Justificativa: a constituição exige snapshot e ledger para auditoria e reconciliação, e esta opção preserva a integridade do estoque histórico.
- **A9 — Option A**: O cadastro do filamento cria automaticamente uma movimentação `ENTRADA` no ledger com o peso cadastrado. Justificativa: a reconciliação e o saldo inicial dependem do ledger como fonte de variação do saldo desde o primeiro registro.
- **A10 — Option C**: O snapshot inclui total na placa e detalhamento por peça na pivot. Justificativa: o total atende ao estorno e à reconciliação, enquanto o detalhe por peça preserva rastreabilidade granular sem desperdiçar capacidade de armazenamento.
- **D2 — Option A**: `products.is_active` existe com `BOOLEAN NOT NULL DEFAULT TRUE`. Justificativa: produtos inativos não entram em novos cadastros de placa, mas placas históricas permanecem válidas sem invalidar chaves estrangeiras.

### Impacted stories and FRs

- **A1** affects: User Story 2, User Story 4, FR-005, FR-006, FR-008, FR-009, FR-010.
- **A2** affects: User Story 3, FR-015, Key Entities (`ProdutoPeca`).
- **D1 → consolidado em A2**: `part_product.quantity` com `CHECK (quantity > 0)`; impacta User Story 3, FR-015 e Key Entities (`ProdutoPeca`).
- **A3** affects: User Story 2, User Story 3, FR-016, FR-021, FR-023, FR-025.
- **A4** affects: User Story 2, User Story 4, FR-021, FR-022, FR-024, FR-037.
- **A9** affects: User Story 2, User Story 4, FR-009, FR-010, FR-025, FR-029, FR-030.
- **A10** affects: User Story 2, User Story 4, FR-021, FR-024, FR-035, Key Entities (`PlacaPeca`, `Placa`).
- **D2** affects: User Story 3, FR-014, FR-015, Key Entities (`Produto`).

## Out of Scope

- Multiusuário com perfis e permissões.
- Vendas, clientes, pedidos e faturamento.
- Manutenção de estoque mínimo ou alertas automáticos.
- Integração com marketplaces, ERP ou gateways de pagamento.
- Relatórios analíticos avançados e gráficos de tendência.
- Impressão de etiquetas, QR Code ou rastreio físico exterior ao sistema.
- App mobile nativo.
- Módulo de margem e precificação automática.

## Acceptance Scenarios Detailed

### Scenario 1 - Idempotência da conclusão

**Given** uma placa no status IMPRIMINDO com peças e quantidades válidas, **When** o usuário conclui a placa duas vezes em sequência ou o backend recebe retry duplicado, **Then** o sistema cria a quantidade correta de itens apenas uma vez, decrementa o saldo apenas uma vez e grava uma única movimentação de CONSUMO no ledger.

### Scenario 2 - Snapshot congelado após edição de peça

**Given** uma placa foi concluída e a peça associada foi editada após a conclusão, **When** o usuário consulta o histórico da placa, **Then** o consumo registrado em `placa.consumo_filamento_g` permanece inalterado e não reflete o novo valor de `peça.filamento_g`.

### Scenario 3 - Bloqueio com saldo insuficiente

**Given** um filamento com saldo menor que o consumo estimado da placa, **When** o usuário tenta concluir a placa, **Then** o sistema bloqueia a conclusão com mensagem clara e mantém `filamento.saldo`, itens e ledger inalterados.

### Scenario 4 - Reconciliação com divergência

**Given** um filamento com `filamento.saldo` divergente de `SUM(movimentacoes.quantidade_g)`, **When** o comando de reconciliação é executado em dry-run, **Then** o sistema lista a divergência; **And When** o comando é executado em apply com confirmação, **Then** ele registra `AJUSTE_RECONCILIACAO` no ledger e não aplica `UPDATE` direto no saldo.

### Scenario 5 - Transições de status inválidas

**Given** uma placa em status CRIADO, **When** o usuário tenta mudar diretamente para CONCLUIDO, **Then** o sistema bloqueia a transição porque a sequência válida exige `CRIADO -> FILA -> IMPRIMINDO -> CONCLUIDO`.

### Scenario 6 - Entrada de filamento

**Given** um novo rolo de filamento cadastrado com peso e valor informados, **When** o cadastro é concluído, **Then** o sistema define `filamento.balance = weight`, grava uma movimentação `ENTRY` no ledger com `previous_balance_g = 0` e `new_balance_g = weight`, tudo na mesma transação.  
**And Given** um filamento já existente, **When** o usuário tenta adicionar saldo sem criar novo rolo, **Then** o sistema não oferece esse fluxo; essa regra é coberta por **FR-010** e pela decisão de que "adicionar filamento" significa criar um NOVO rolo.

## Final Confirmation

A especificação anterior, baseada em modelo derivado, foi descartada e não é mantida. O modelo adotado neste documento é o modelo do meio fixado pela constituição: saldo persistido como cache materializado, snapshot em `placa.consumo_filamento_g`, ledger append-only e reconciliação obrigatória. Esta é a base funcional válida para implementação no sistema.

## Corrections Applied

- **Correção 1 — A1=A sem AddFilamentEntry**: o flux de "adicionar filamento" foi definido como criação de um NOVO rolo; o saldo de rolos existentes só é alterado por `CONSUMO`, `ESTORNO` ou `AJUSTE_RECONCILIACAO`. Impacta User Story 2, FR-010 e a linha de estrutura do schema em `filaments`/`filament_movements`.
- **Correção 2 — ledger append-only**: `filament_movements.updated_at` foi removido do schema para refletir o ledger imutável. Impacta a tabela `filament_movements` no data-model e a descrição do append-only no spec.
- **Correção 3 — snapshot em reabertura/recompletação**: `consumed_filament_g` é redefinido em cada conclusão e limpo em reabertura; o ledger preserva o histórico de cada conclusão e reversão. Impacta User Story 2, FR-021, FR-022, FR-024 e a seção do data-model sobre snapshot.
- **Correção 4 — source_id semantics**: `source_id` agora foi documentado para `ENTRY`, `PLATE` e `RECONCILIATION` em `data-model.md`.
- **Correção 5 — integridade**: `part_product.quantity > 0`, `part_plate.quantity > 0`, `filament_movements.quantity_g <> 0` e `new_balance_g = previous_balance_g + quantity_g` foram adicionados ao schema. Impacta `part_product`, `part_plate` e `filament_movements`.
- **Correção 6 — índices faltantes**: índices em `part_plate(part_id)`, `filament_movements(type)` e `part_product(part_id)` foram adicionados para consulta e filtros do ledger/composição.
- **Correção 7 — products.is_active**: `products.is_active BOOLEAN NOT NULL DEFAULT TRUE` foi incluído e documentado como política para novos cadastros de placa sem invalidar placas históricas.
- **Correção 8 — known technical debt**: seção "Known Technical Debt" adicionada no data-model para registrar `sort_order`, `items`, `part_product` e idempotência do ledger. 
