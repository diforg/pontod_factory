# Quickstart

## 1. Setup with Docker

```bash
docker compose up -d --build
cp .env.example .env
# adjust APP_KEY / DB credentials if needed

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

docker compose exec app npm install
docker compose exec app npm run build
```

## 2. Minimum seed flow

The default seed should create:

- 2 filaments
- 3 parts
- 2 products
- 1 plate in queue

## 3. Manual happy path

1. Log in with the seeded user.
2. Go to Filaments and create a rolo.
   - system creates `filament.code` using the pattern.
   - system writes the initial `ENTRY` movement automatically.
   - `balance` becomes `weight` in the same transaction.
3. Create two or more parts and assign them to a product.
4. Create a product with `part_product.quantity` values.
5. Create a plate for a filament in `CREATED` state.
6. Move the plate through `QUEUED` and `PRINTING`.
7. Complete the plate.
8. Verify the board:
   - `plates.consumed_filament_g` is populated;
   - `part_plate.consumed_filament_g` is populated for each part;
   - stock `items` rows are created;
   - `filaments.balance` is decremented exactly once;
   - one `CONSUMPTION` movement is recorded in `filament_movements`.
9. Reopen the same plate.
   - confirm `REVERSAL` movement is written;
   - confirm the original stock items are removed;
   - confirm the status returns to `PRINTING`.
10. Run the reconciliation command:

```bash
docker compose exec app php artisan filaments:reconcile
docker compose exec app php artisan filaments:reconcile --apply
```

Verify:

- the dry-run output lists differences;
- the apply mode creates `RECONCILIATION_ADJUSTMENT` entries instead of direct updates.

## 4. Manual test for important edge cases

### Idempotent completion

- call the completion action twice;
- verify no duplicate item creation and no double decrement.

### Invalid transitions

- attempt `CREATED -> COMPLETED` directly;
- confirm the transition is rejected.

### Frozen historical snapshot

- create a plate and complete it;
- edit a part `filament_g` afterward;
- verify the completed plate still reflects the original frozen consumption.

### Insufficient balance

- set a filament balance below the projected plate consumption;
- complete the plate;
- verify the transaction is rejected and no side effects are recorded.

## Reconciliation

To check for divergence between the stored balance and the ledger:

```bash
docker compose exec app php artisan filaments:reconcile
```

This runs in dry-run mode by default - it lists divergences without modifying anything. To apply corrections:

```bash
docker compose exec app php artisan filaments:reconcile --apply
```

Corrections are recorded as `RECONCILIATION_ADJUSTMENT` movements in the ledger; the stored balance is updated by the same movement (never by a direct `UPDATE` bypassing the ledger).
