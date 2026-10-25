# Tasks — Gestão de Produção 3D

## Phase 0 — Setup [INFRA]

- [X] T001 [INFRA]: Dockerfile multi-stage (build de assets + runtime PHP 8.3).
  - Files: `docker/Dockerfile`
  - Completion: `docker build` succeeds and the runtime image includes PHP 8.3 + Node asset build tooling.

- [X] T002 [INFRA] [P]: docker-compose.yml com serviços `app`, `mysql` 8.4 e `node`, com volume persistente do MySQL.
  - Files: `docker/docker-compose.yml`
  - Completion: `docker compose up -d` starts the app and database services with persistent data volume.

- [X] T003 [INFRA]: `.env.example` com `DB_*`, `APP_KEY`, `APP_URL`, `APP_ENV`.
  - Files: `.env.example`
  - Completion: sample environment includes all required Laravel app and database configuration keys.

- [X] T004 [INFRA]: `bin/setup` para `composer install`, `key:generate`, `migrate --seed`, `npm install`, `npm run build`.
  - Files: `bin/setup`
  - Completion: the setup script completes end-to-end in a clean environment and boots the app without fixups.

- [X] T005 [INFRA]: Instalar Laravel 11 + Breeze (Inertia/Vue) + Pest + Pint + ESLint + Prettier + Vitest + Vue Draggable Next; configurar scripts em `package.json` e `composer.json`.
  - Files: `composer.json`, `package.json`
  - Completion: the application bootstraps with the selected stack and all configured scripts run without dependency errors.

- [X] T006 [INFRA]: Configurar `routes/web.php` e `routes/auth.php` com middleware `auth` no grupo protegido.
  - Files: `routes/web.php`, `routes/auth.php`
  - Completion: unauthenticated users are redirected to the login page and protected routes require authentication.

- Phase 0 completion: `docker compose up` + `bin/setup` boots the app with the login page working and the database migrated to the Breeze base schema.

## Phase 1 — Data Foundation [US2, US4, US5]

- [X] T007 [US2]: Migration `users` (padrão Breeze + `remember_token`), se necessário para o schema do Breeze.
  - Files: `database/migrations/*_create_users_table.php`
  - Completion: the users table exists with the Breeze auth structure and can be rolled back cleanly.

- [X] T008 [US2]: Migration `filaments` com `CHECK (balance >= 0)`, índices `(material, brand, color)` e `(is_active)`.
  - Files: `database/migrations/*_create_filaments_table.php`
  - Completion: migration creates the filaments table with positive balance guard and required indexes.

- [X] T009 [US5]: Migration `filament_movements` com FKs para `filaments.code` e `users.id`, `CHECK`s (`quantity_g <> 0` e `new_balance_g = previous_balance_g + quantity_g`), índices `(filament_code, occurred_at)`, `(source_type, source_id)`, `(type)`, e sem `updated_at`.
  - Files: `database/migrations/*_create_filament_movements_table.php`
  - Completion: the ledger table is append-only by design and can be migrated and rolled back without errors.

- [X] T010 [US2]: Migration `parts` com `UNIQUE` em `code` e `is_active`.
  - Files: `database/migrations/*_create_parts_table.php`
  - Completion: parts table exists with unique code and active flag.

- [X] T011 [US2]: Migration `products` com `UNIQUE` em `code` e `is_active`.
  - Files: `database/migrations/*_create_products_table.php`
  - Completion: products table exists with unique code and active flag.

- [X] T012 [US2]: Migration `part_product` com `quantity INT NOT NULL DEFAULT 1`, `CHECK (quantity > 0)`, índice em `part_id`.
  - Files: `database/migrations/*_create_part_product_table.php`
  - Completion: product-to-part composition supports quantity and retains valid values only.

- [X] T013 [US2]: Migration `plates` com FK para `filaments.code`, enum status, `sort_order`, `consumed_filament_g` nullable, `completed_at` nullable, `CHECK (consumed_filament_g IS NULL OR consumed_filament_g >= 0)`, índices `(status, sort_order)` e `(filament_code)`.
  - Files: `database/migrations/*_create_plates_table.php`
  - Completion: plate lifecycle table is created with order and snapshot fields and valid status constraints.

- [X] T014 [US2]: Migration `part_plate` com `quantity`, `consumed_filament_g` nullable, `CHECK (quantity > 0)`, índice em `part_id`.
  - Files: `database/migrations/*_create_part_plate_table.php`
  - Completion: the many-to-many plate/part mapping stores quantity and frozen per-piece snapshot support.

- [X] T015 [US2]: Migration `items` com FKs para `parts.id` e `plates.id`, índices em `part_id` e `plate_id`.
  - Files: `database/migrations/*_create_items_table.php`
  - Completion: stock items are persisted with traceable part/plate links.

- [X] T016 [US2]: Models Eloquent: `Filament`, `FilamentMovement`, `Part`, `Product`, `Plate`, `Item`; definir relacionamentos (`belongsTo`, `hasMany`, `belongsToMany` via `part_product` e `part_plate`).
  - Files: `app/Models/*.php`
  - Completion: all models expose expected relationships used by controllers, actions, and tests.

- [X] T017 [US5]: Em `FilamentMovement::booted()`, bloquear `update` e `delete` com exceção. Teste unitário cobrindo a proteção do ledger.
  - Files: `app/Models/FilamentMovement.php`, `tests/Unit/FilamentMovementTest.php`
  - Completion: any update or delete on a ledger row raises an exception and the unit test passes.

- [X] T018 [US2] [P]: Enums em `app/Enums`: `PlateStatus`, `MovementType`, `MovementSourceType`.
  - Files: `app/Enums/*.php`
  - Completion: enums exist for all plate statuses and movement types and are used consistently across the codebase.

- Phase 1 completion: `php artisan migrate:fresh` runs successfully, `migrate:rollback` reverses cleanly, and Pest passes for the ledger protection tests.

## Phase 2 — Domain Support [US2]

- [X] T019 [US2]: Teste unitário de `FilamentCodeGenerator` cobrindo: formato `MATERIAL-BRAND-COLOR-NNNN`, incremental atômico sob concorrência simulada e unicidade sob lock/transação.
  - Files: `tests/Unit/FilamentCodeGeneratorTest.php`
  - Completion: the test is written before implementation and covers collision safety under concurrent generation attempts.

- [X] T020 [US2]: Implementar `App\Support\FilamentCodeGenerator` usando transação + lock (SELECT MAX por prefixo ou tabela de sequência).
  - Files: `app/Support/FilamentCodeGenerator.php`
  - Completion: `T019` passes and generator creates collision-safe codes in the required format.

- [X] T021 [US2]: Teste unitário de `PlateSnapshotService` cobrindo: cálculo de `consumed_filament_g` (soma de `part.filament_g × quantity`), snapshot total na placa e por peça na pivot, e comportamento após edição de `part.filament_g` (não deve retroagir).
  - Files: `tests/Unit/PlateSnapshotServiceTest.php`
  - Completion: the test is written before implementation and covers the historical snapshot semantics.

- [X] T022 [US2]: Implementar `App\Services\PlateSnapshotService`.
  - Files: `app/Services/PlateSnapshotService.php`
  - Completion: `T021` passes and the service freezes per-plate consumption without retroactive mutation.

- Phase 2 completion: Pest covers the generator and snapshot scenarios and both services are independent from HTTP and controllers.

## Phase 3 — Business Actions [US2, US4]

- [X] T023 [US2]: Teste Feature `CreateFilament` cobrindo criação de rolo com `balance = weight`, `ENTRY` com `previous_balance_g = 0`, `new_balance_g = weight`, `source_id = NULL`, `source_type = ENTRY`, operação em transação única, e rollback em falha.
  - Files: `tests/Feature/Filaments/CreateFilamentTest.php`
  - Completion: the test is written before implementation and describes the expected single-rolo creation behavior.

- [X] T024 [US2]: Implementar `App\Actions\CreateFilament`.
  - Files: `app/Actions/CreateFilament.php`
  - Completion: `T023` passes and creates a new filament roll with the correct default balance and ledger entry.

- [X] T025 [US2]: Teste Feature `CompletePlate` cobrindo: congela `consumed_filament_g` total e por peça, cria itens (1 por unidade por peça), decrementa `filaments.balance`, grava `CONSUMPTION` no ledger com previous/new balance, muda status para `COMPLETED`, rollback total em falha, e idempotência (segunda chamada não duplica itens, saldo nem movimento).
  - Files: `tests/Feature/Plates/CompletePlateTest.php`
  - Completion: the test is written before implementation and captures the atomic completion + idempotence contract.

- [X] T026 [US2]: Implementar `App\Actions\CompletePlate` com `DB::transaction` + `lockForUpdate` no filamento + guard de status.
  - Files: `app/Actions/CompletePlate.php`
  - Completion: `T025` passes and completion is atomic, balance-checked, and idempotent.

- [X] T027 [US2]: Teste Feature `CompletePlate` — bloqueio por saldo insuficiente: `saldo < consumo` lança exceção, saldo/items/ledger inalterados, status permanece `PRINTING`.
  - Files: `tests/Feature/Plates/CompletePlateInsufficientBalanceTest.php`
  - Completion: the failing test is created before implementation and passes after validation logic is added.

- [X] T028 [US2]: Implementar validação de saldo em `CompletePlate`.
  - Files: `app/Actions/CompletePlate.php`
  - Completion: `T027` passes and no invalid completion writes any side effects.

- [X] T029 [US2]: Teste Feature `ReopenPlate` cobrindo: limpa `plates.consumed_filament_g` e `part_plate.consumed_filament_g`, remove `items`, grava `REVERSAL` no ledger usando o snapshot congelado, volta status para `PRINTING`, e idempotência (segunda chamada não faz nada).
  - Files: `tests/Feature/Plates/ReopenPlateTest.php`
  - Completion: the test is written before implementation and validates reversal semantics and idempotence.

- [X] T030 [US2]: Implementar `App\Actions\ReopenPlate`.
  - Files: `app/Actions/ReopenPlate.php`
  - Completion: `T029` passes and reopening reverts the plate without duplicate bookkeeping.

- [X] T031 [US4]: Teste Feature `ReconcileFilaments` cobrindo dry-run, `--apply` com `RECONCILIATION_ADJUSTMENT`, `source_type = RECONCILIATION`, `source_id = NULL`, `previous/new balance` corretos, ausência de `UPDATE` direto em `filaments.balance`, e filamentos consistentes sem movimentação.
  - Files: `tests/Feature/Reconciliation/ReconcileFilamentsTest.php`
  - Completion: the test is written before implementation and covers both dry-run and apply paths.

- [X] T032 [US4]: Implementar `App\Actions\ReconcileFilaments`.
  - Files: `app/Actions/ReconcileFilaments.php`
  - Completion: `T031` passes and reconcile updates the ledger without mutating balance directly.

- Phase 3 completion: all business Action tests (`T023`, `T025`, `T027`, `T029`, `T031`) pass and business-action coverage is at least 80%.

## Phase 4 — HTTP Layer [US1, US2, US3]

- T033 [US1]: Configurar Breeze com `remember me` ativo. Teste Feature: login com remember gera cookie persistente; logout invalida.
  - Files: `config/auth.php`, `routes/auth.php`, `tests/Feature/Auth/LoginTest.php`
  - Completion: persistent login and logout work and the feature test passes.

- T034 [US2]: Form Requests: `StoreFilamentRequest`, `UpdateFilamentRequest`.
  - Files: `app/Http/Requests/*.php`
  - Completion: validation rules are in place and covered by feature tests.

- T035 [US2]: `FilamentController` (`index`, `store`, `update`, `activate`, `deactivate`) + testes Feature por endpoint.
  - Files: `app/Http/Controllers/FilamentController.php`, `tests/Feature/Filaments/*.php`
  - Completion: all filament endpoints execute with expected validation and persistence semantics.

- T036 [US2]: Form Requests: `StorePartRequest`, `UpdatePartRequest`; `PartController` + testes.
  - Files: `app/Http/Requests/*.php`, `app/Http/Controllers/PartController.php`, `tests/Feature/Parts/*.php`
  - Completion: CRUD flows for parts work and validation passes.

- T037 [US2]: Form Requests: `StoreProductRequest`, `UpdateProductRequest`; `ProductController` + testes.
  - Files: `app/Http/Requests/*.php`, `app/Http/Controllers/ProductController.php`, `tests/Feature/Products/*.php`
  - Completion: product creation and quantity association work and validation passes.

- T038 [US2]: Form Requests: `StorePlateRequest`, `UpdatePlateStatusRequest`, `ReorderPlateRequest`; `PlateController` (`index`, `store`, `update`, `updateStatus`, `reorder`) + testes.
  - Files: `app/Http/Requests/*.php`, `app/Http/Controllers/PlateController.php`, `tests/Feature/Plates/*.php`
  - Completion: plate status transitions and queue reordering are validated and persisted.

- T039 [US5]: `ItemController` + ledger controller (movements) com filtros por `type` e período + testes.
  - Files: `app/Http/Controllers/*.php`, `tests/Feature/Ledger/*.php`
  - Completion: ledger endpoints filter correctly and return only valid movement history.

- T040 [US3]: `DashboardController` retornando payload único com filamentos ativos + saldo, placas não concluídas ordenadas e produtos com contagem de itens por peça + testes.
  - Files: `app/Http/Controllers/DashboardController.php`, `tests/Feature/Dashboard/*.php`
  - Completion: dashboard data is aggregated correctly and the page renders valid production information.

- T041 [US2]: Rotas em `routes/web.php` cobrindo os endpoints acima, sob `middleware auth`.
  - Files: `routes/web.php`
  - Completion: all protected routes are wired and unauthorized requests are blocked.

- Phase 4 completion: every controller has feature tests and all routes are protected and validated.

## Phase 5 — Frontend [US1, US2, US3, US5]

- T042 [US1]: Página `Auth/Login.vue` com checkbox para “manter logado”.
  - Files: `resources/js/Pages/Auth/Login.vue`
  - Completion: the login form includes persistent session control and authenticated sessions behave as expected.

- T043 [US2] [P]: Página `Filaments/Index.vue` com listagem, criar novo rolo, ativar/desativar. Componente `FilamentCard.vue`.
  - Files: `resources/js/Pages/Filaments/Index.vue`, `resources/js/Components/FilamentCard.vue`
  - Completion: filament CRUD flows work through the UI and reflect real ledger balance changes.

- T044 [US2] [P]: Página `Parts/Index.vue` com CRUD.
  - Files: `resources/js/Pages/Parts/Index.vue`
  - Completion: part creation/editing works and persisted data matches backend rules.

- T045 [US2] [P]: Página `Products/Index.vue` com CRUD + associação de peças.
  - Files: `resources/js/Pages/Products/Index.vue`
  - Completion: the product composition is persisted and quantity on `part_product` is reflected correctly.

- T046 [US2]: Página `Plates/Index.vue` com CRUD, mudança de status e aviso visual quando o consumo estimado excede o saldo (A3=B).
  - Files: `resources/js/Pages/Plates/Index.vue`
  - Completion: plate creation and status updates work and warnings trigger correctly before completion.

- T047 [US3] [P]: `PlateDragList.vue` com Vue Draggable Next; persistir reorder via endpoint.
  - Files: `resources/js/Components/PlateDragList.vue`
  - Completion: drag-and-drop persists sort order and survives reload.

- T048 [US3] [P]: `ProductCollapse.vue` com peças e quantidade disponível.
  - Files: `resources/js/Components/ProductCollapse.vue`
  - Completion: product expansion shows part quantities and available stock without stale values.

- T049 [US3]: `Dashboard/Index.vue` compondo filamentos, placas e products.
  - Files: `resources/js/Pages/Dashboard/Index.vue`
  - Completion: dashboard loads the aggregated production snapshot and remains readable.

- T050 [US5]: Página de ledger/movimentações com filtros por `type` e período.
  - Files: `resources/js/Pages/Ledger/Index.vue`
  - Completion: the ledger page renders movement data with type and period filtering.

- T051 [US2]: `StatusBadge.vue` com tradução de enum → rótulo PT-BR.
  - Files: `resources/js/Components/StatusBadge.vue`
  - Completion: statuses are displayed with localized labels and consistent mapping.

- T052 [INFRA]: Componente reutilizável de flash/erro para feedback de operações críticas (conclusão bloqueada por saldo, reconciliação aplicada).
  - Files: `resources/js/Components/FlashMessage.vue`
  - Completion: critical business messages display consistently on the UI.

- Phase 5 completion: the full production flow is executable through the UI in Docker, drag-and-drop persists after reload, and flash messages work.

## Phase 6 — Reconciliation Command [US4]

- T053 [US4]: `App\Console\Commands\ReconcileFilaments` como wrapper fino que chama `App\Actions\ReconcileFilaments`. Flags `--apply` e `--dry-run` (default). Teste de comando via Pest (`artisan call`).
  - Files: `app/Console/Commands/ReconcileFilaments.php`, `tests/Feature/Reconciliation/ReconcileFilamentsCommandTest.php`
  - Completion: `php artisan filaments:reconcile --dry-run` and `--apply` behave according to `T031` and the command is documented in quickstart.

- Phase 6 completion: reconciliation command works in dry-run and apply modes as defined by the business rules.

## Phase 7 — Seed & Manual Validation [INFRA]

- T054 [INFRA]: Seeder com dados do arquivo original: 2 filamentos (`PLA-MULTFILA-ROSA-0001`, `PLA-MULTFILA-PRETO-0001`), 3 peças (`NUV1-DOMO_INFERIOR_A/B/C`), 2 produtos (`NUVP1 P`, `NUVP2 M`), 1 placa em `PRINTING`.
  - Files: `database/seeders/*.php`
  - Completion: the seed set creates the baseline production data and is idempotent when re-run.

- T055 [INFRA]: Executar o cenário do quickstart ponta a ponta em ambiente Docker limpo e validar: criar filament → criar part → criar product → criar plate → concluir → verificar balance + items + ledger → reabrir → verificar `REVERSAL` → reconciliação dry-run/apply.
  - Files: `database/seeders/*.php`, `docs/quickstart.md`
  - Completion: the documented manual validation passes in a clean Docker environment.

- Phase 7 completion: the quickstart is reproducible and the seed set is safe to re-run in local Docker.

## Phase 8 — Tests, CI, Quality Gates [INFRA]

- T056 [INFRA]: Configurar GitHub Actions (ou equivalente) rodando `Pint --test`, `ESLint`, `Pest`, `Vitest` em `push` e `PR`.
  - Files: `.github/workflows/*.yml`
  - Completion: CI runs the backend and frontend quality gates and fails on the relevant test or lint issues.

- T057 [INFRA]: Teste de integração end-to-end cobrindo o caminho feliz completo (Breeze login → criar filament → criar part → criar product → criar plate → concluir → verificar estado → reabrir → reconciliar).
  - Files: `tests/Feature/*.php`
  - Completion: the end-to-end flow passes with the complete production cycle in a real app run.

- T058 [INFRA]: Verificar cobertura >= 80% nas Actions de negócio (Pest `--coverage` com gate no CI).
  - Files: `phpunit.xml`, `.github/workflows/*.yml`
  - Completion: the test coverage gate passes and outputs a visible threshold report.

- T059 [INFRA]: Constitution check final: revisar que os 12 princípios (I, II, III, IV, V, VI, VII, VIII, IX, A, B, C) estão cobertos por testes e implementação, conforme o plan.md.
  - Files: `spec.md`, `specs/001-gestao-producao-3d/plan.md`, `tests/**/*.php`
  - Completion: constitution review is recorded and every principle is validated by implementation or tests.

- Phase 8 completion: CI is green, all quality gates pass, and reconciliation is executable and tested.

## Dependency Map

- T001 bloqueia T002.
- T002 bloqueia T004 e T005.
- T003 bloqueia T004.
- T004 bloqueia T006.
- T005 bloqueia T006, T033, T042.
- T006 bloqueia T033 e T041.
- T007 bloqueia T017, T023, T024, T033.
- T008 bloqueia T009, T023, T024, T026.
- T009 bloqueia T017, T023, T024, T026, T031, T032.
- T010 bloqueia T014, T036.
- T011 bloqueia T012, T037.
- T012 bloqueia T045, T037.
- T013 bloqueia T014, T025, T026, T029, T030, T038.
- T014 bloqueia T021, T022, T025, T026, T029, T030.
- T015 bloqueia T025, T026, T029, T030, T039.
- T016 bloqueia T020, T022, T024, T026, T030, T032, T035, T036, T037, T038, T039, T040.
- T017 bloqueia T023, T024, T025, T026, T029, T030, T031, T032.
- T018 bloqueia T025, T026, T029, T030, T031, T032, T038.
- T019 bloqueia T020.
- T020 bloqueia T023, T024.
- T021 bloqueia T022.
- T022 bloqueia T025, T026.
- T023 bloqueia T024.
- T024 bloqueia T035.
- T025 bloqueia T026.
- T026 bloqueia T028, T038, T046.
- T027 bloqueia T028.
- T028 bloqueia T038.
- T029 bloqueia T030.
- T030 bloqueia T038, T046.
- T031 bloqueia T032.
- T032 bloqueia T053.
- T033 bloqueia T042.
- T034 bloqueia T035.
- T035 bloqueia T043.
- T036 bloqueia T045.
- T037 bloqueia T045.
- T038 bloqueia T046, T047.
- T039 bloqueia T050.
- T040 bloqueia T049.
- T041 bloqueia T042, T043, T044, T045, T046, T047, T048, T049, T050.
- T042 bloqueia T049.
- T043 bloqueia T049.
- T044 bloqueia T045.
- T045 bloqueia T049.
- T046 bloqueia T047.
- T047 bloqueia T049.
- T048 bloqueia T049.
- T050 bloqueia T055.
- T052 bloqueia T049.
- T053 bloqueia T055.
- T054 bloqueia T055.
- T055 bloqueia T057.
- T056 bloqueia T058, T059.
- T057 bloqueia T058.
- T058 bloqueia T059.

## Rastreabilidade para spec.md

- US1 (Auth): T033, T041, T042, T052.
- US2 (Ciclo de produção): T007, T008, T009, T010, T011, T012, T013, T014, T015, T016, T017, T018, T019, T020, T021, T022, T023, T024, T025, T026, T027, T028, T029, T030, T034, T035, T036, T037, T038, T041, T043, T044, T045, T046, T051.
- US3 (Dashboard): T040, T047, T048, T049, T052.
- US4 (Reconciliação): T009, T017, T031, T032, T053, T055, T057, T059.
- US5 (Ledger): T009, T017, T039, T050.

## Final confirmation

- (a) A ordem de migrations respeita as dependências de FK: users → filaments → filament_movements → parts → products → part_product → plates → part_plate → items. ✅
- (b) Toda tarefa de negócio tem teste escrito antes da implementação: explicitamente indicado em `T019`, `T021`, `T023`, `T025`, `T027`, `T029`, `T031`, e as demais Actions do plano. ✅
- (c) O ledger está protegido antes das Actions que escrevem nele: `T017` vem antes das tarefas de criação/complete/reopen/reconcile. ✅
- (d) Idempotência de conclusão e reabertura estão cobertas em testes: `T025` e `T029` definem e validam o comportamento esperado. ✅
- (e) Reconciliação tem teste dry-run e apply: `T031` cobre ambos os caminhos. ✅
- (f) As 9 fases do plano estão representadas: Phase 0 a Phase 8. ✅
