# Phase 0 Research

## 1. Technical stack and library versions

- PHP 8.3
- Laravel 11
- Inertia 2
- Vue 3 with Composition API and `<script setup>`
- MySQL 8.4
- Docker + docker-compose
- Vite (standard Laravel build)
- Laravel Breeze for authentication with remember-me cookie
- Pest PHP for backend tests
- Vitest for frontend tests
- Vue Draggable Next for queue ordering
- Laravel Pint + ESLint + Prettier

## 2. Business model decisions

### Filament identity

The model chosen by clarifications is A1=A: each filament record represents one physical rolo. This means:

- `filaments.code` is the immutable identifier for the rolo.
- `filaments.balance` is a materialized cache.
- `filament_movements` references the rolo via `filament_code`.
- The balance by material type is the aggregation of active rolos, not a standalone domain object.

### Plate creation validation

Decision A3=B: warning at plate creation; hard validation only at completion. This maintains flexibility for planning while still preserving the constitution rule that `balance` can never be negative as a result of completion.

### Reopening a completed plate

Decision A4=B: reopen is allowed through a transaction that:

1. locks the plate and filament rows;
2. removes generated stock items;
3. writes a `REVERSAL` movement with the frozen consumption snapshot;
4. restores the status to `PRINTING`;
5. does not allow duplicate reopens.

This is supported by `plates.consumed_filament_g` and `part_plate.consumed_filament_g`.

### Initial filament entry

Decision A9=A: when a filament is created, the application inserts a `ENTRY` ledger movement in the same transaction with `quantity_g = weight` and `balance = weight`. This ensures the ledger and the cache are synchronized from the start and reconciliation can detect drifts immediately.

### Product composition quantity

Decision A2=A: the product-to-part relationship includes `part_product.quantity` with a positive check. This allows a product to require multiple units of the same part while still keeping the relationship explicit and auditable.

### Snapshot granularity

Decision A10=C: both the total and per-part snapshots are kept:

- `plates.consumed_filament_g` for overall plate consumption.
- `part_plate.consumed_filament_g` for each part in the plate.

This supports estorno, reconciliation, and historical traceability without reconstructing data from the current part definition.

## 3. Idempotency and locking strategy

### Completion of a plate

The completion action must follow this pattern:

```sql
UPDATE plates
SET status = 'COMPLETED'
WHERE id = ? AND status = 'PRINTING';
```

If `affected_rows = 0`, the operation has already occurred or a race condition was won by another transaction. The action should abort without creating a second item set or ledger movement.

### Locking strategy

- Lock the filament row with `lockForUpdate()` before checking available balance.
- Lock the plate row and related rows in the same transaction.
- Keep all reads and writes within `DB::transaction()`.
- Use database-level status checks rather than trusting UI state.

### Reopening logic

Reopen follows the same pattern:

```sql
UPDATE plates
SET status = 'PRINTING'
WHERE id = ? AND status = 'COMPLETED';
```

If zero rows are updated, the plate was already reopened or the transition is invalid.

## 4. Filament code generation

`filaments.code` uses the format `MATERIAL-BRAND-COLOR-NNNN`.

Implementation approach:

- Prefix is built from material + brand + color.
- Use a single-row `filament_code_sequence` table or a locked select/insert pattern to reserve the next number in a transaction.
- The generator must be atomic and collision-safe in concurrent environments.

## 5. Ledger strategy

`filament_movements` is append-only. The model should protect updates and deletes:

```php
protected static function booted(): void
{
    static::updating(fn () => throw new Exception('Ledger entries are immutable.'));
    static::deleting(fn () => throw new Exception('Ledger entries are immutable.'));
}
```

Every balance-changing action must create exactly one movement in the same transaction as the balance update:

- `ENTRY` on filament creation and new rolo input;
- `CONSUMPTION` on plate completion;
- `REVERSAL` on plate reopening;
- `RECONCILIATION_ADJUSTMENT` on reconciliation apply.

## 6. Reconciliation command

The command `php artisan filaments:reconcile --apply` will:

1. iterate over filaments;
2. sum `filament_movements.quantity_g` for the filament;
3. compare with `filaments.balance`;
4. print the dry-run difference or create a compensating `RECONCILIATION_ADJUSTMENT` in apply mode;
5. write a clear audit log with the expected and persisted values.

## 7. Product composition and `part_product.quantity`

Decision: include `quantity` in the pivot `part_product` because the product is made from parts in quantities, not only by presence/absence. This is the cleanest representation for product structures: one product can include N instances of one part.

Example:

- product A has `part 1 x 2`, `part 2 x 3`, `part 3 x 1`

This quantity is independent from the plate composition quantity, which is a separate relation at the production batch level.

## 8. UI and business flow decisions

- The project uses a single Laravel monolith with Inertia and Vue pages.
- Authentication is standard Breeze + remember me.
- The dashboard must show active filaments, queue order, and expandable products.
- UI remains focused on speed and clarity; drag-and-drop is operational, not decorative.
- Minimal seed set: 2 filaments, 3 parts, 2 products, 1 plate.

## 9. Out-of-scope decisions for v1

- cron jobs and queue workers;
- marketplace or customer integrations;
- sales tracking, invoices or pricing automation;
- multi-user roles/permissions;
- advanced analytics and alerting.

## 10. Validation strategy

The work will be considered acceptable only when the following are green before release:

- Pest feature tests for completion, idempotency, reopening, invalid status transitions and initial entry processing.
- Unit tests for filament code generation and snapshot math.
- Pint and ESLint pass.
- Docker reproducibility is confirmed by a clean setup from the repository.
