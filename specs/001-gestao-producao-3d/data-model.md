# Data Model

## Domain model

This model is expressed in English and follows the constitution and clarified decisions:

- `filaments` represent one rolo per row (A1=A)
- `plates` keep total and per-part frozen snapshot at completion (A10=C)
- `filament_movements` is append-only and acts as the source of balance variation; corrections are compensating movements only (`REVERSAL` or `RECONCILIATION_ADJUSTMENT`)
- `plate` completion is atomic and idempotent; reopening is allowed with reversal (A4=B)
- `products` support soft deactivation via `is_active` without invalidating historical plates (D2=A)
- the snapshot is the operational state of the current completion; the ledger is the historical audit source

## Entity model

### filaments

```sql
CREATE TABLE filaments (
    code VARCHAR(64) PRIMARY KEY,
    brand VARCHAR(64) NOT NULL,
    material VARCHAR(32) NOT NULL,
    color VARCHAR(32) NOT NULL,
    weight DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CHECK (balance >= 0)
);

CREATE INDEX idx_filaments_material_brand_color ON filaments (material, brand, color);
CREATE INDEX idx_filaments_is_active ON filaments (is_active);
```

### filament_movements

```sql
CREATE TABLE filament_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filament_code VARCHAR(64) NOT NULL,
    type ENUM('ENTRY', 'CONSUMPTION', 'REVERSAL', 'RECONCILIATION_ADJUSTMENT') NOT NULL,
    quantity_g DECIMAL(10,2) NOT NULL,
    previous_balance_g DECIMAL(10,2) NOT NULL,
    new_balance_g DECIMAL(10,2) NOT NULL,
    source_type ENUM('ENTRY', 'PLATE', 'RECONCILIATION') NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    occurred_at DATETIME NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    CONSTRAINT fk_filament_movements_filament
        FOREIGN KEY (filament_code) REFERENCES filaments(code),
    CONSTRAINT fk_filament_movements_user
        FOREIGN KEY (user_id) REFERENCES users(id),
    CHECK (quantity_g <> 0),
    CHECK (new_balance_g = previous_balance_g + quantity_g)
);

CREATE INDEX idx_filament_movements_filament_time ON filament_movements (filament_code, occurred_at);
CREATE INDEX idx_filament_movements_source ON filament_movements (source_type, source_id);
CREATE INDEX idx_filament_movements_type ON filament_movements (type);
```

### parts

```sql
CREATE TABLE parts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    size VARCHAR(16) NULL,
    filament_g DECIMAL(10,2) NOT NULL,
    production_time_min INT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE INDEX idx_parts_code ON parts (code);
```

### products

```sql
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(255) NULL,
    size VARCHAR(16) NULL,
    version VARCHAR(16) NULL,
    sale_price DECIMAL(10,2) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE INDEX idx_products_code ON products (code);
CREATE INDEX idx_products_is_active ON products (is_active);
```

### part_product

```sql
CREATE TABLE part_product (
    product_id BIGINT UNSIGNED NOT NULL,
    part_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    PRIMARY KEY (product_id, part_id),
    CONSTRAINT fk_part_product_product
        FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_part_product_part
        FOREIGN KEY (part_id) REFERENCES parts(id),
    CHECK (quantity > 0)
);

CREATE INDEX idx_part_product_part_id ON part_product (part_id);
```

### plates

```sql
CREATE TABLE plates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filament_code VARCHAR(64) NOT NULL,
    status ENUM('CREATED', 'QUEUED', 'PRINTING', 'COMPLETED') NOT NULL DEFAULT 'CREATED',
    sort_order INT NOT NULL,
    consumed_filament_g DECIMAL(10,2) NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_plates_filament
        FOREIGN KEY (filament_code) REFERENCES filaments(code),
    CHECK (consumed_filament_g IS NULL OR consumed_filament_g >= 0)
);

CREATE INDEX idx_plates_status_sort ON plates (status, sort_order);
CREATE INDEX idx_plates_filament ON plates (filament_code);
```

### part_plate

```sql
CREATE TABLE part_plate (
    plate_id BIGINT UNSIGNED NOT NULL,
    part_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    consumed_filament_g DECIMAL(10,2) NULL,
    PRIMARY KEY (plate_id, part_id),
    CONSTRAINT fk_part_plate_plate
        FOREIGN KEY (plate_id) REFERENCES plates(id),
    CONSTRAINT fk_part_plate_part
        FOREIGN KEY (part_id) REFERENCES parts(id),
    CHECK (quantity > 0)
);

CREATE INDEX idx_part_plate_part_id ON part_plate (part_id);
```

### items

```sql
CREATE TABLE items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_id BIGINT UNSIGNED NOT NULL,
    plate_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    CONSTRAINT fk_items_part
        FOREIGN KEY (part_id) REFERENCES parts(id),
    CONSTRAINT fk_items_plate
        FOREIGN KEY (plate_id) REFERENCES plates(id)
);

CREATE INDEX idx_items_part ON items (part_id);
CREATE INDEX idx_items_plate ON items (plate_id);
```

## source_id semantics

`filament_movements.source_id` has explicit semantics based on `source_type`:

- `source_type = 'ENTRY'` → `source_id = NULL` because the originating row is the filament itself (`filament_code` is the source)
- `source_type = 'PLATE'` → `source_id = plates.id`
- `source_type = 'RECONCILIATION'` → `source_id = NULL` because the adjustment is recorded in the reconciliation command log and not tied to a single plate row

This makes the ledger auditable without coupling it to an arbitrary row relationship that would not exist for all movement types.

## Enums and constraints

- `status` in `plates`: `CREATED`, `QUEUED`, `PRINTING`, `COMPLETED`
- `type` in `filament_movements`: `ENTRY`, `CONSUMPTION`, `REVERSAL`, `RECONCILIATION_ADJUSTMENT`
- `source_type` in `filament_movements`: `ENTRY`, `PLATE`, `RECONCILIATION`
- `balance` must be non-negative
- `sort_order` must be non-null and deterministic
- `consumed_filament_g` is set on each successful completion and cleared on reopen; historical values are preserved in `filament_movements` as `CONSUMPTION` and `REVERSAL`, while the snapshot always reflects the most recent valid completion
- `part_product.quantity` and `part_plate.quantity` must be positive
- `filament_movements.quantity_g` must be non-zero and `new_balance_g` must equal `previous_balance_g + quantity_g`

## Relationships

- One filament can have many movements
- One filament can be used by many plates
- One plate can contain many parts through `part_plate`
- One product can contain many parts through `part_product`
- One part can appear in many products and many plates
- One plate can generate many stock items; each item belongs to one part and one plate

## Derived rules

- `filaments.balance = SUM(movements.quantity_g)` (in a consistent ledger state)
- `plates.consumed_filament_g` = sum of part_plate consumption snapshot for that plate
- `part_plate.consumed_filament_g` is frozen at the most recent completion and cleared on reopen; it is not recalculated from current part definitions when part `filament_g` changes later
- `items` are created only during a successful atomic completion transaction

## Known Technical Debt

- `plates.sort_order` is not unique at the database level; the frontend is expected to send distinct values in v1, but the DB does not enforce uniqueness.
- `items` grows one row per produced unit; this is acceptable for the small-business volume in v1 and can be revisited in a later model when stock volume or archive requirements increase.
- `part_product` has no timestamps; this is acceptable for v1, but a later version may require `created_at` and `updated_at` for audit of composition changes.
- `filament_movements` has no idempotence key such as `(source_id, type)` for plate-generated movements; idempotence is ensured by the database transaction and the status guard (`UPDATE ... WHERE status = 'PRINTING'`), which is sufficient for v1. If the strategy changes, add a unique constraint for plate-driven `CONSUMPTION` and `REVERSAL` entries.
