# Implementation Plan: Gestão de Produção 3D

**Branch**: `001-gestao-producao-3d` | **Date**: 2026-10-21 | **Spec**: `../spec.md`

**Input**: Feature specification from `/spec.md` with clarifications A1=A, A3=B, A4=B, A9=A, A10=C.

## Summary

This implementation delivers a Laravel 11 + Inertia 2 + Vue 3 + MySQL 8.4 production management system for a 3D print micro-business. The plan is grounded in the constitution v3.x model: filament balance is a persisted cache, plate consumption is frozen in a snapshot at completion, every balance change is written to an append-only ledger, and reconciliation is a mandatory manual command. The project will prioritize the critical production flow: filament registration, part creation, product composition, plate creation, queue order, status progression, completion transaction, stock generation, ledger auditability, and reconciliation.

## Technical Context

**Language/Version**: PHP 8.3 + Laravel 11; JavaScript with Vue 3 and Inertia 2; MySQL 8.4; Docker + docker-compose; Vite for asset compilation.

**Primary Dependencies**: Laravel Breeze (Inertia/Vue), Pest PHP, Vitest, Vue Draggable Next, Laravel Pint, ESLint, Prettier, MySQL client/driver, Docker compose.

**Storage**: MySQL 8.4; append-only ledger table; persisted materialized balance cache on `filaments.balance`.

**Testing**: Pest PHP for backend; Vitest for frontend; contract and feature tests covering idempotency, reconciliation, invalid status transitions, snapshot integrity and ledger correctness.

**Target Platform**: Linux-based local/containerized development environment; production assumptions are aligned with Dockerized deployment in the same repository.

**Project Type**: Web application.

**Performance Goals**: Fast local operations for small business workflows; queue reorder and dashboard rendering should remain responsive for hundreds of rows, not enterprise-scale workloads.

**Constraints**: Must comply with constitution 3.0.0; no direct balance editing; no implicit recomputation from historical plates; plate completion must be atomic and idempotent; all balance changes require ledger entries; Dockerized environment must be reproducible.

**Scale/Scope**: Small operation with a few dozen filaments, parts and products; queue management and dashboard as the operational focus; no scheduled workers or marketplace flows in v1.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Principle coverage

| Principle | Coverage in plan | Status |
|---|---|---|
| I. Cache materialized + snapshot | `filaments.balance`, `plates.consumed_filament_g`, `part_plate.consumed_filament_g`, transaction rules and reconciliation flow are defined in architecture and data model | Pass |
| II. Idempotence of plate completion | Completion and reopening use transaction + guarded status updates; idempotent checks documented in research, actions and tests | Pass |
| III. Explicit plate lifecycle and ledger | Lifecycle `CREATED -> QUEUED -> PRINTING -> COMPLETED` is enforced; ledger auditability included in schema and actions | Pass |
| IV. Composition traceability | `part_product`, `part_plate`, `items` relationships and movement history traceability defined | Pass |
| V. Composite identifiers | `filaments.code` pattern and atomic incremental generation specified | Pass |
| VI. Authentication and session | Laravel Breeze with `remember me` and `auth` middleware are part of architecture | Pass |
| VII. Simplicity for entrepreneur | Dashboard, queue ordering and collapsible products are planned in pages and components | Pass |
| VIII. Tests as contract | TDD and required tests listed in research, migration and validation plan | Pass |
| IX. Containerization | Docker compose services, app/mysql/node, `.env.example`, build flow in quickstart and architecture | Pass |
| A. Model of filament entries | Each rolo is a filament row; ledger references filament_code; initial entry is auto-created | Pass |
| B. Ledger append-only | Append-only ledger model, no UPDATE/DELETE, reconciliation via compensating adjustment | Pass |
| C. Reconciliation | Manual `filaments:reconcile {--apply}` command and dry-run/apply flow defined | Pass |

### Gaps / pending items

No constitutional gaps are identified in the plan. The only remaining work is to implement the specified tests and run them before release.

## Project Structure

### Documentation (this feature)

```text
specs/001-gestao-producao-3d/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── openapi.yaml
├── spec.md
└── tasks.md    # not created in this plan; reserved for /speckit.tasks
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── CreateFilament.php
│   ├── CompletePlate.php
│   ├── ReopenPlate.php
│   └── ReconcileFilaments.php    # business logic; single source of truth for reconciliation
├── Console/Commands/
│   └── ReconcileFilaments.php    # thin CLI wrapper; delegates to ReconcileFilaments Action
├── Http/Controllers/
│   ├── AuthController.php
│   ├── DashboardController.php
│   ├── FilamentController.php
│   ├── PartController.php
│   ├── ProductController.php
│   ├── PlateController.php
│   └── ItemController.php
├── Http/Requests/
│   ├── StoreFilamentRequest.php
│   ├── UpdateFilamentRequest.php
│   ├── StorePlateRequest.php
│   ├── UpdatePlateStatusRequest.php
│   └── ReconcileRequest.php
├── Models/
│   ├── Filament.php
│   ├── FilamentMovement.php
│   ├── Part.php
│   ├── Product.php
│   ├── Plate.php
│   ├── Item.php
│   └── User.php
├── Policies/
│   └── optional-if-multiuser
├── Services/
│   └── PlateSnapshotService.php
└── Support/
    └── FilamentCodeGenerator.php

resources/
├── js/
│   ├── Components/
│   │   ├── FilamentCard.vue
│   │   ├── PlateDragList.vue
│   │   ├── ProductCollapse.vue
│   │   ├── StatusBadge.vue
│   │   └── PlateList.vue
│   ├── Pages/
│   │   ├── Auth/Login.vue
│   │   ├── Dashboard/Index.vue
│   │   ├── Filaments/Index.vue
│   │   ├── Parts/Index.vue
│   │   ├── Products/Index.vue
│   │   └── Plates/Index.vue
│   └── composables/
│       └── usePlateQueue.ts
├── css/app.css
└── views/

routes/
├── web.php
├── auth.php
└── api.php

database/
├── factories/
├── migrations/
├── seeders/
└── schema/

tests/
├── Feature/
│   ├── Auth/
│   ├── Filaments/
│   ├── Plates/
│   ├── Reconciliation/
│   └── Dashboard/
├── Unit/
│   ├── FilamentCodeGeneratorTest.php
│   └── PlateSnapshotTest.php
└── Pest.php

docker/
├── Dockerfile
├── docker-compose.yml
├── .env.example
└── scripts/
    └── setup.sh

public/
└── assets/
```

**Structure Decision**: Laravel monolith with Inertia pages and Vue components; no separate frontend application. The backend owns business rules while Vue is limited to UI orchestration, list rendering and drag-and-drop interactions.

## Domain-to-Code Mapping

| Business term (PT-BR) | Technical name (EN) |
|---|---|
| Filamento | Filament |
| Movimento de filamento | FilamentMovement |
| Peça | Part |
| Produto | Product |
| Placa | Plate |
| Item em estoque | Item |
| Código de filamento | code |
| Saldo | balance |
| Peso do rolo | weight |
| Valor do rolo | price |
| Peso consumido do filamento | consumed_filament_g |
| Ordem da fila | sort_order |
| Status da placa | status |
| Criado | CREATED |
| Fila | QUEUED |
| Imprimindo | PRINTING |
| Concluído | COMPLETED |
| Entrada | ENTRY |
| Consumo | CONSUMPTION |
| Estorno | REVERSAL |
| Ajuste de reconciliação | RECONCILIATION_ADJUSTMENT |
| Produto por peça | part_product |
| Relação placa/peça | part_plate |
| Entidade de produção | Item |
| Dashboard | DashboardController |
| Reordenação por drag-drop | PlateDragList.vue |
| Reconcile | ReconcileFilaments |

## Phases

### Phase 0 — Research and validation

- Confirm final library versions and operational patterns.
- Verify Laravel 11/Breeze + Vue 3 + Inertia 2 support details.
- Confirm MySQL 8.4 and Docker setup.
- Confirm `part_product.quantity` as enabled (already decided in A2=A / D1=A); no further research needed.
- Validate `lockForUpdate` + database-level idempotency rules for plate completion and reopening.
- Review the rebuild strategy for `filament.code` generation and collision safety.

### Phase 1 — Data model and contracts

- Create the migration set with strict English naming and append-only ledger constraints.
- Add unique constraints for code fields, check on `balance >= 0`, and foreign keys.
- Define OpenAPI contracts for dashboard, filament, part, product, plate and reconciliation endpoints.
- Define request validation for critical operations: store filament, store plate, status transition, reconciliation apply.

### Phase 2 — Business actions and UI

- Implement the action classes for filament creation, plate completion, reopening and reconciliation.
- Implement Inertia controllers and Vue pages.
- Add drag-and-drop queue ordering and expandable product cards.
- Add dashboard widgets with real-time values from persisted balance and coherent stock counts.

### Phase 3 — Quality gates and verification

- Write failing tests first for idempotency, snapshot integrity, invalid transitions and reconciliation.
- Run Pest and Vitest; run Pint/ESLint.
- Validate Docker setup and app booting with migration + seeding.
- Perform final constitution check before advancing to tasks.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Double completion of plate | Duplicate items and ledger entries | Guard status update in transaction and idempotent completion action |
| Direct saldo editing | Historical inconsistency | Prohibit all application-side edits; only ledger-driven changes |
| Wrong snapshot after part edits | Incorrect historical stock | Freeze `consumed_filament_g` at completion and keep part detail snapshots |
| Concurrent balance updates | Negative balance or lost updates | `lockForUpdate` on filament row and conditional decrement semantics |
| Reconciliation drift | Hidden stock errors | Mandatory `filaments:reconcile` command with dry-run + apply modes |
| Drag-drop ordering loss | Queue inconsistency | Persist `sort_order` as a real database field |

## Complexity Tracking

No constitution violations were identified. Architecture remains within the configured constraints and does not require justifying exceptions.

---

This plan is traced to [../spec.md](../spec.md) and to the v3.0.0 constitution in [.specify/memory/constitution.md](../../.specify/memory/constitution.md).

## Corrections Applied

- **Correção 1 — A1=A sem AddFilamentEntry**: a criação de filamento foi tratada como criação de um NOVO rolo; a operação de incrementar saldo em filamento existente foi removida do plano. Impacta a estrutura de `Actions` e a lógica do fluxo de produção no plano.
- **Correção 2 — ledger append-only**: `filament_movements.updated_at` foi removido do schema principal para refletir o ledger imutável e a ausência de updates em histórico. Impacta a tabela `filament_movements` em [data-model.md](data-model.md).
- **Correção 3 — snapshot em reabertura/recompletação**: a execução do reabrir limpa o snapshot operacional e regrava em nova conclusão; o ledger continua sendo a fonte histórica. Impacta [spec.md](../spec.md) e [data-model.md](data-model.md).
- **Correção 4 — source_id semantics**: a semântica de `source_id` foi documentada explicitamente em [data-model.md](data-model.md) para `ENTRY`, `PLATE` e `RECONCILIATION`.
- **Correção 5 — integridade**: `part_product.quantity > 0`, `part_plate.quantity > 0`, `filament_movements.quantity_g <> 0` e `new_balance_g = previous_balance_g + quantity_g` foram adicionados ao schema.
- **Correção 6 — índices faltantes**: índices em `part_product(part_id)`, `part_plate(part_id)` e `filament_movements(type)` foram incluídos no modelo.
- **Correção 7 — products.is_active**: `products.is_active` foi documentado e alinhado à política de desativação sem invalidar o histórico de placas.
- **Correção 8 — known technical debt**: a seção de dívida técnica foi incluída em [data-model.md](data-model.md) para registrar os itens de v1 no banco.
