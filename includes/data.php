<?php
/**
 * DermAve IMS - data layer.
 *
 * Prefers the MySQL `directory_items` table (via db.php).
 * Falls back to in-memory sample rows that mirror the SQL seed
 * so the UI works even before phpMyAdmin is set up.
 */

require_once __DIR__ . '/db.php';

/* ------------------------------------------------------------------
 * Status helpers (unchanged logic, used by product/machine pages)
 * ----------------------------------------------------------------*/

/**
 * Product status from expiry date.
 * @return array [css class, label, days left]
 */
function product_status(string $expiry): array
{
    $today = new DateTimeImmutable('today');
    $exp   = new DateTimeImmutable($expiry);
    $days  = (int) $today->diff($exp)->format('%r%a');

    if ($days < 0) {
        return ['expired', 'Expired', $days];
    }
    if ($days <= 30) {
        $label = $days === 0 ? 'Today' : $days . ' day' . ($days === 1 ? '' : 's');
        return ['soon', $label, $days];
    }
    return ['ok', 'OK', $days];
}

/**
 * Machine maintenance status.
 * @return array [css class, label, days left]
 */
function machine_status(string $date): array
{
    [$class, $label, $days] = product_status($date);
    return $class === 'expired' ? ['overdue', 'Overdue', $days] : [$class, $label, $days];
}

/**
 * Supply stock level.
 * @return string 'out' | 'low' | 'ok'
 */
function supply_status(int $stock, int $min): string
{
    if ($stock <= 0) {
        return 'out';
    }
    return $stock <= $min ? 'low' : 'ok';
}

/* ------------------------------------------------------------------
 * Sample data (matches sql/directory_schema.sql)
 * ----------------------------------------------------------------*/

function _sample_directory_items(): array
{
    $today = new DateTimeImmutable('today');

    return [
        // Machines
        [
            'id' => 1, 'item_type' => 'machine', 'name' => 'Dermaplaning Handpiece',
            'brand' => null, 'category' => 'Machines', 'subcategory' => 'Facial / Skin',
            'description' => 'Handheld dermaplaning device', 'dosage' => null, 'unit' => 'unit',
            'quantity' => 1, 'min_stock' => null, 'price' => null, 'batch_number' => null,
            'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => $today->modify('-20 days')->format('Y-m-d'),
            'next_maintenance' => $today->modify('+40 days')->format('Y-m-d'),
            'status' => 'active', 'notes' => 'Routine blade/head check',
        ],
        [
            'id' => 2, 'item_type' => 'machine', 'name' => 'Plasma Fibroblast Pen',
            'brand' => null, 'category' => 'Machines', 'subcategory' => 'Facial / Skin',
            'description' => 'Plasma fibroblast treatment pen', 'dosage' => null, 'unit' => 'unit',
            'quantity' => 1, 'min_stock' => null, 'price' => null, 'batch_number' => null,
            'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => $today->modify('-45 days')->format('Y-m-d'),
            'next_maintenance' => $today->modify('-10 days')->format('Y-m-d'),
            'status' => 'active', 'notes' => 'Overdue for tip inspection',
        ],
        [
            'id' => 3, 'item_type' => 'machine', 'name' => 'LED Face Mask / Dome',
            'brand' => null, 'category' => 'Machines', 'subcategory' => 'Facial / Light Therapy',
            'description' => 'LED face mask or dome unit', 'dosage' => null, 'unit' => 'unit',
            'quantity' => 2, 'min_stock' => null, 'price' => null, 'batch_number' => null,
            'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => $today->modify('-10 days')->format('Y-m-d'),
            'next_maintenance' => $today->modify('+50 days')->format('Y-m-d'),
            'status' => 'active', 'notes' => null,
        ],
        [
            'id' => 4, 'item_type' => 'machine', 'name' => 'HydraFacial Machine',
            'brand' => null, 'category' => 'Skin Care & Facial Treatments', 'subcategory' => 'Facial Machines',
            'description' => 'HydraFacial treatment system', 'dosage' => null, 'unit' => 'unit',
            'quantity' => 1, 'min_stock' => null, 'price' => null, 'batch_number' => null,
            'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => $today->modify('-5 days')->format('Y-m-d'),
            'next_maintenance' => $today->modify('+25 days')->format('Y-m-d'),
            'status' => 'active', 'notes' => null,
        ],
        [
            'id' => 5, 'item_type' => 'machine', 'name' => 'Cryolipolysis / CoolSculpting Machine',
            'brand' => null, 'category' => 'Body Contouring & Fat Reduction', 'subcategory' => 'Fat Reduction',
            'description' => 'Cryolipolysis body contouring device', 'dosage' => null, 'unit' => 'unit',
            'quantity' => 1, 'min_stock' => null, 'price' => null, 'batch_number' => null,
            'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => $today->modify('-30 days')->format('Y-m-d'),
            'next_maintenance' => $today->modify('+60 days')->format('Y-m-d'),
            'status' => 'active', 'notes' => 'Applicator seals checked',
        ],

        // Products
        [
            'id' => 6, 'item_type' => 'product', 'name' => 'Glutathione Vials',
            'brand' => null, 'category' => 'IV Drip & Injection Consumables', 'subcategory' => 'Vitamins / Injectables',
            'description' => 'Glutathione for IV push/drip (verify indication & authorization)',
            'dosage' => '600 mg', 'unit' => 'vial', 'quantity' => 24, 'min_stock' => 10, 'price' => 450.00,
            'batch_number' => 'GLU-2401',
            'manufacturing_date' => $today->modify('-90 days')->format('Y-m-d'),
            'expiration_date' => $today->modify('+180 days')->format('Y-m-d'),
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => 'Also available 1.2 g / 5 g / 8 g',
        ],
        [
            'id' => 7, 'item_type' => 'product', 'name' => 'Vitamin C (Ascorbic Acid) Ampoules',
            'brand' => null, 'category' => 'IV Drip & Injection Consumables', 'subcategory' => 'Vitamins / Injectables',
            'description' => 'Ascorbic acid for IV / mesotherapy / topical',
            'dosage' => '500 mg', 'unit' => 'ampoule', 'quantity' => 40, 'min_stock' => 15, 'price' => 85.00,
            'batch_number' => 'VTC-118',
            'manufacturing_date' => $today->modify('-60 days')->format('Y-m-d'),
            'expiration_date' => $today->modify('+120 days')->format('Y-m-d'),
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => 'Also 3 g strength',
        ],
        [
            'id' => 8, 'item_type' => 'product', 'name' => 'Normal Saline (0.9% NaCl)',
            'brand' => null, 'category' => 'IV Drip & Injection Consumables', 'subcategory' => 'IV Fluids and Solutions',
            'description' => '0.9% sodium chloride IV solution',
            'dosage' => '500 mL', 'unit' => 'bag', 'quantity' => 30, 'min_stock' => 12, 'price' => 35.00,
            'batch_number' => 'NS-500-092',
            'manufacturing_date' => $today->modify('-30 days')->format('Y-m-d'),
            'expiration_date' => $today->modify('+365 days')->format('Y-m-d'),
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => 'Also 100/250/1000 mL',
        ],
        [
            'id' => 9, 'item_type' => 'product', 'name' => 'Hyaluronic Acid (Skin Booster / Filler)',
            'brand' => null, 'category' => 'Topical & Procedural Consumables', 'subcategory' => 'Injectable / Skin Rejuvenation',
            'description' => 'HA for filler or skin booster / mesotherapy',
            'dosage' => '~20 mg/mL', 'unit' => 'syringe 1 mL', 'quantity' => 18, 'min_stock' => 5, 'price' => 2200.00,
            'batch_number' => 'HA-1ML-07',
            'manufacturing_date' => $today->modify('-45 days')->format('Y-m-d'),
            'expiration_date' => $today->modify('+300 days')->format('Y-m-d'),
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => '0.5 / 1 / 2 mL sizes',
        ],
        [
            'id' => 10, 'item_type' => 'product', 'name' => 'Botulinum Toxin Vials',
            'brand' => null, 'category' => 'Topical & Procedural Consumables', 'subcategory' => 'Injectable / Skin Rejuvenation',
            'description' => 'Botulinum toxin (requires appropriate licensing)',
            'dosage' => '100 units', 'unit' => 'vial', 'quantity' => 8, 'min_stock' => 3, 'price' => 8500.00,
            'batch_number' => 'BTX-100-03',
            'manufacturing_date' => $today->modify('-20 days')->format('Y-m-d'),
            'expiration_date' => $today->modify('+90 days')->format('Y-m-d'),
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => 'Also 50 / 200 unit vials',
        ],

        // Supplies
        [
            'id' => 11, 'item_type' => 'supply', 'name' => 'Syringes (Assorted Sizes)',
            'brand' => null, 'category' => 'IV Administration Equipment', 'subcategory' => 'IV Admin Supplies',
            'description' => 'Disposable sterile syringes – specify sizes on order',
            'dosage' => null, 'unit' => 'pcs', 'quantity' => 120, 'min_stock' => 40, 'price' => 8.50,
            'batch_number' => null, 'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => 'Common sizes 1 mL / 3 mL / 5 mL / 10 mL',
        ],
        [
            'id' => 12, 'item_type' => 'supply', 'name' => 'Sterile Needles (Assorted Sizes)',
            'brand' => null, 'category' => 'IV Administration Equipment', 'subcategory' => 'IV Admin Supplies',
            'description' => 'Disposable sterile needles – specify gauges/lengths',
            'dosage' => null, 'unit' => 'pcs', 'quantity' => 200, 'min_stock' => 50, 'price' => 3.25,
            'batch_number' => null, 'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => null,
        ],
        [
            'id' => 13, 'item_type' => 'supply', 'name' => 'Alcohol Swabs',
            'brand' => null, 'category' => 'IV Administration Equipment', 'subcategory' => 'IV Admin Supplies',
            'description' => 'Single-use alcohol prep pads',
            'dosage' => null, 'unit' => 'box', 'quantity' => 25, 'min_stock' => 10, 'price' => 45.00,
            'batch_number' => null, 'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => null,
        ],
        [
            'id' => 14, 'item_type' => 'supply', 'name' => 'IV Bags (empty / administration sets related)',
            'brand' => null, 'category' => 'IV Administration Equipment', 'subcategory' => 'IV Admin Supplies',
            'description' => 'IV administration bags / related consumables',
            'dosage' => null, 'unit' => 'pcs', 'quantity' => 40, 'min_stock' => 15, 'price' => 28.00,
            'batch_number' => null, 'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => 'Distinct from pre-filled fluid bags',
        ],
        [
            'id' => 15, 'item_type' => 'supply', 'name' => 'Numbing Cream (Lidocaine 5%)',
            'brand' => null, 'category' => 'Anesthetics and Topical Preparations', 'subcategory' => 'Topical Anesthetics',
            'description' => 'Topical lidocaine cream 4–5%',
            'dosage' => null, 'unit' => 'tube', 'quantity' => 12, 'min_stock' => 4, 'price' => 180.00,
            'batch_number' => null, 'manufacturing_date' => null, 'expiration_date' => null,
            'last_maintenance' => null, 'next_maintenance' => null,
            'status' => 'active', 'notes' => 'Also gel and EMLA available',
        ],
    ];
}

/* ------------------------------------------------------------------
 * Directory API
 * ----------------------------------------------------------------*/

/**
 * Fetch all directory items (or filtered).
 *
 * @param array $opts  Optional: item_type, status, include_archived (bool)
 * @return array
 */
function get_directory_items(array $opts = []): array
{
    $type            = $opts['item_type'] ?? null;
    $status          = $opts['status'] ?? null;
    $includeArchived = !empty($opts['include_archived']);

    $pdo = db();
    if ($pdo) {
        $sql    = 'SELECT * FROM directory_items WHERE 1=1';
        $params = [];

        if ($type) {
            $sql .= ' AND item_type = ?';
            $params[] = $type;
        }
        if ($status) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        } elseif (!$includeArchived) {
            $sql .= " AND status = 'active'";
        }

        $sql .= ' ORDER BY item_type, name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Fallback sample data
    $rows = _sample_directory_items();
    if ($type) {
        $rows = array_values(array_filter($rows, fn($r) => $r['item_type'] === $type));
    }
    if ($status) {
        $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $status));
    } elseif (!$includeArchived) {
        $rows = array_values(array_filter($rows, fn($r) => $r['status'] === 'active'));
    }
    return $rows;
}

/**
 * Distinct values for a column (used by clickable header filters).
 */
function get_directory_distinct(string $column): array
{
    $allowed = ['item_type', 'category', 'subcategory', 'brand', 'unit', 'status'];
    if (!in_array($column, $allowed, true)) {
        return [];
    }

    $pdo = db();
    if ($pdo) {
        $stmt = $pdo->query("SELECT DISTINCT `$column` AS val FROM directory_items WHERE `$column` IS NOT NULL AND `$column` != '' ORDER BY val");
        return array_column($stmt->fetchAll(), 'val');
    }

    $vals = [];
    foreach (_sample_directory_items() as $row) {
        if (!empty($row[$column])) {
            $vals[$row[$column]] = true;
        }
    }
    $list = array_keys($vals);
    sort($list);
    return $list;
}

/* ------------------------------------------------------------------
 * Backward-compatible getters for the existing pages
 * ----------------------------------------------------------------*/

function get_products(): array
{
    $items = get_directory_items(['item_type' => 'product']);
    return array_map(function ($r) {
        return [
            'item'   => $r['name'],
            'type'   => $r['subcategory'] ?: ($r['category'] ?? ''),
            'brand'  => $r['brand'] ?? '',
            'dosage' => $r['dosage'] ?? '',
            'mfg'    => $r['manufacturing_date'] ?? '',
            'batch'  => $r['batch_number'] ?? '',
            'expiry' => $r['expiration_date'] ?? date('Y-m-d', strtotime('+1 year')),
            'price'  => $r['price'] !== null ? (float) $r['price'] : null,
            'qty'    => (int) $r['quantity'],
        ];
    }, $items);
}

function get_machines(): array
{
    $items = get_directory_items(['item_type' => 'machine']);
    return array_map(function ($r) {
        return [
            'name' => $r['name'],
            'last' => $r['last_maintenance'] ?? '',
            'date' => $r['next_maintenance'] ?? date('Y-m-d', strtotime('+30 days')),
        ];
    }, $items);
}

function get_supplies(): array
{
    $items = get_directory_items(['item_type' => 'supply']);
    return array_map(function ($r) {
        return [
            'item'  => $r['name'],
            'type'  => $r['subcategory'] ?: ($r['category'] ?? ''),
            'stock' => (int) $r['quantity'],
            'unit'  => $r['unit'] ?? 'pcs',
            'min'   => (int) ($r['min_stock'] ?? 0),
        ];
    }, $items);
}

/**
 * Format a date for the tables, or an em dash when empty.
 */
function table_date(?string $d): string
{
    $ts = $d ? strtotime($d) : false;
    return $ts ? date('M d, Y', $ts) : '—';
}

/* ------------------------------------------------------------------
 * Directory writes (Add / Edit / Archive) - need the MySQL database
 * ----------------------------------------------------------------*/

/**
 * Which columns each item type actually uses. Anything else is stored as NULL,
 * so switching an item's type never leaves stray values behind.
 */
function directory_type_fields(): array
{
    $common = ['name', 'brand', 'category', 'subcategory', 'unit', 'quantity', 'notes'];
    return [
        'product' => array_merge($common, ['barcode', 'dosage', 'min_stock', 'price', 'batch_number', 'manufacturing_date', 'expiration_date']),
        'machine' => array_merge($common, ['last_maintenance', 'next_maintenance']),
        'supply'  => array_merge($common, ['barcode', 'min_stock', 'price', 'expiration_date']),
    ];
}

/**
 * Check + clean submitted form data.
 *
 * @param  array $in      raw input (e.g. $_POST)
 * @param  array $errors  filled with human-readable messages
 * @return array          column => value, ready for the database
 */
function directory_validate(array $in, array &$errors): array
{
    $errors = [];
    $get = fn(string $k): string => trim((string) ($in[$k] ?? ''));

    $type = $get('item_type');
    $map  = directory_type_fields();
    if (!isset($map[$type])) {
        $errors[] = 'Please choose a type (product, machine or supply).';
        return [];
    }

    // text columns: column => [label, max length]
    $texts = [
        'name'         => ['Name', 255],
        'brand'        => ['Brand', 120],
        'category'     => ['Category', 150],
        'subcategory'  => ['Subcategory', 150],
        'dosage'       => ['Dosage', 120],
        'unit'         => ['Unit', 40],
        'batch_number' => ['Batch number', 80],
        'barcode'      => ['Barcode', 64],
        'notes'        => ['Notes', 2000],
    ];
    $data = ['item_type' => $type];

    foreach ($texts as $col => [$label, $max]) {
        $v = $get($col);
        if (mb_strlen($v) > $max) {
            $errors[] = "$label must be $max characters or less.";
        }
        $data[$col] = $v === '' ? null : $v;
    }
    if ($data['name'] === null) {
        $errors[] = 'Name is required.';
    }

    if ($data['barcode'] !== null && !preg_match('/^[\x20-\x7E]+$/', $data['barcode'])) {
        $errors[] = 'Barcode can only use normal letters, numbers and symbols.';
    }

    // whole numbers
    $q = $get('quantity');
    if ($q === '') {
        $data['quantity'] = 0;
    } elseif (filter_var($q, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]) === false) {
        $errors[] = 'Quantity must be a whole number from 0 to 1,000,000.';
        $data['quantity'] = 0;
    } else {
        $data['quantity'] = (int) $q;
    }

    $m = $get('min_stock');
    if ($m === '') {
        $data['min_stock'] = null;
    } elseif (filter_var($m, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]) === false) {
        $errors[] = 'Minimum stock must be a whole number from 0 to 1,000,000.';
        $data['min_stock'] = null;
    } else {
        $data['min_stock'] = (int) $m;
    }

    // price
    $p = str_replace(',', '', ltrim($get('price'), '₱'));
    if ($p === '') {
        $data['price'] = null;
    } elseif (!is_numeric($p) || (float) $p < 0 || (float) $p > 9999999999.99) {
        $errors[] = 'Price must be a number of 0 or more.';
        $data['price'] = null;
    } else {
        $data['price'] = number_format((float) $p, 2, '.', '');
    }

    // dates (YYYY-MM-DD)
    $dates = [
        'manufacturing_date' => 'Manufacturing date',
        'expiration_date'    => 'Expiration date',
        'last_maintenance'   => 'Last maintenance',
        'next_maintenance'   => 'Next maintenance',
    ];
    foreach ($dates as $col => $label) {
        $v = $get($col);
        if ($v === '') {
            $data[$col] = null;
            continue;
        }
        $d = DateTime::createFromFormat('Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            $errors[] = "$label is not a valid date.";
            $data[$col] = null;
        } else {
            $data[$col] = $v;
        }
    }

    // blank out columns that don't apply to this type
    $allowed = $map[$type];
    foreach (['barcode','brand', 'category', 'subcategory', 'dosage', 'unit', 'quantity', 'min_stock', 'price',
              'batch_number', 'manufacturing_date', 'expiration_date', 'last_maintenance',
              'next_maintenance', 'notes'] as $col) {
        if (!in_array($col, $allowed, true)) {
            $data[$col] = null;
        }
    }

    if ($data['manufacturing_date'] && $data['expiration_date'] && $data['expiration_date'] <= $data['manufacturing_date']) {
        $errors[] = 'Expiration date must be after the manufacturing date.';
    }
    if ($data['last_maintenance'] && $data['next_maintenance'] && $data['next_maintenance'] < $data['last_maintenance']) {
        $errors[] = 'Next maintenance cannot be before the last maintenance.';
    }

    return $data;
}

/**
 * Insert a new item ($id = null) or update an existing one.
 * Items that have batches always get their quantity / expiry recalculated
 * from those batches afterwards.
 *
 * @return int|null  the item id, or null if an update targeted a missing item
 * @throws InvalidArgumentException if the barcode belongs to another item
 */
function directory_save(PDO $pdo, array $data, ?int $id = null): ?int
{
    $cols = ['item_type', 'barcode', 'name', 'brand', 'category', 'subcategory', 'dosage', 'unit', 'quantity',
             'min_stock', 'price', 'batch_number', 'manufacturing_date', 'expiration_date',
             'last_maintenance', 'next_maintenance', 'notes'];
 
    $params = [];
    foreach ($cols as $c) {
        $params[$c] = $data[$c] ?? null;
    }
 
    // A barcode can only belong to one item
    if (!empty($params['barcode'])) {
        $dup = $pdo->prepare('SELECT id FROM directory_items WHERE barcode = ? AND id <> ?');
        $dup->execute([$params['barcode'], $id ?? 0]);
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException('That barcode is already used by another item.');
        }
    }
 
    if ($id === null) {
        $sql = 'INSERT INTO directory_items (' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')';
        $pdo->prepare($sql)->execute($params);
        return (int) $pdo->lastInsertId();
    }
 
    $exists = $pdo->prepare('SELECT 1 FROM directory_items WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetchColumn()) {
        return null;
    }
 
    $set = implode(', ', array_map(fn($c) => "$c = :$c", $cols));
    $params['id'] = $id;
    $pdo->prepare("UPDATE directory_items SET $set WHERE id = :id")->execute($params);
 
    // Quantity / expiry come from the batches when the item has any
    directory_recalc_item($pdo, $id);
    return $id;
}
/**
 * Archive or restore items.
 *
 * @param  int[]  $ids
 * @param  string $status 'archived' | 'active'
 * @return int    rows changed
 */
function directory_set_status(PDO $pdo, array $ids, string $status): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
    if (!$ids || !in_array($status, ['active', 'archived'], true)) {
        return 0;
    }
    $marks = implode(', ', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare("UPDATE directory_items SET status = ? WHERE id IN ($marks)");
    $stmt->execute(array_merge([$status], $ids));
    return $stmt->rowCount();
}

/* ------------------------------------------------------------------
 * Dashboard numbers
 * ----------------------------------------------------------------*/

/**
 * Counts for the dashboard cards. Archived items are never counted.
 *
 *  products / machines / supplies : how many different items of each type
 *  low_stock       : products + supplies with some left, but at or below min_stock
 *  out_of_stock    : products + supplies with none left
 *  expired         : products + supplies already past their expiration date
 *  expiring_30/60/90 : days left 0-30, 31-60, 61-90 (the ranges do not overlap)
 *  expiring_soon   : the three ranges added together
 *  maintenance_due : machines overdue or due within 30 days
 *  maintenance_overdue : machines already past their next maintenance date
 */
function dashboard_stats(): array
{
    $today = new DateTimeImmutable('today');

    // Days from today until $date (negative = already past), or null if no date
    $daysLeft = function (?string $date) use ($today): ?int {
        if (!$date) {
            return null;
        }
        return (int) $today->diff(new DateTimeImmutable($date))->format('%r%a');
    };

    $s = [
        'products' => 0, 'machines' => 0, 'supplies' => 0,
        'low_stock' => 0, 'out_of_stock' => 0,
        'expired' => 0, 'expiring_30' => 0, 'expiring_60' => 0, 'expiring_90' => 0,
        'maintenance_due' => 0, 'maintenance_overdue' => 0,
    ];
    $countKey = ['product' => 'products', 'machine' => 'machines', 'supply' => 'supplies'];

    foreach (get_directory_items() as $r) {      // active items only
        $type = $r['item_type'];
        $s[$countKey[$type]]++;

        // Machines: maintenance
        if ($type === 'machine') {
            $days = $daysLeft($r['next_maintenance'] ?? null);
            if ($days !== null && $days < 0) {
                $s['maintenance_overdue']++;
            }
            if ($days !== null && $days <= 30) {
                $s['maintenance_due']++;
            }
            continue;
        }

        // Products and supplies: stock level
        $qty = (int) $r['quantity'];
        $min = $r['min_stock'] ?? null;
        if ($qty <= 0) {
            $s['out_of_stock']++;
        } elseif ($min !== null && $qty <= (int) $min) {
            $s['low_stock']++;
        }

        // Products and supplies: expiry
        $days = $daysLeft($r['expiration_date'] ?? null);
        if ($days === null) {
            continue;
        }
        if ($days < 0) {
            $s['expired']++;
        } elseif ($days <= 30) {
            $s['expiring_30']++;
        } elseif ($days <= 60) {
            $s['expiring_60']++;
        } elseif ($days <= 90) {
            $s['expiring_90']++;
        }
    }

    $s['expiring_soon'] = $s['expiring_30'] + $s['expiring_60'] + $s['expiring_90'];
    return $s;
}

/* ------------------------------------------------------------------
 * Barcode + batches
 * ----------------------------------------------------------------*/
 
/** True if $v is a real calendar date written as YYYY-MM-DD. */
function is_valid_ymd(string $v): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}
 
/**
 * Find a product or supply by barcode (archived ones included, so the
 * caller can tell the user). Returns null when not found / no database.
 */
function get_item_by_barcode(string $barcode): ?array
{
    $pdo = db();
    if (!$pdo || $barcode === '') {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM directory_items WHERE barcode = ? AND item_type IN ('product', 'supply') LIMIT 1");
    $stmt->execute([$barcode]);
    $row = $stmt->fetch();
    return $row ?: null;
}
 
/** All batches of one item, soonest expiry first. */
function get_batches(int $itemId): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM item_batches WHERE item_id = ? ORDER BY expiration_date, batch_number');
    $stmt->execute([$itemId]);
    return $stmt->fetchAll();
}
 
/** The most recently saved batches, with their item name and type. */
function get_recent_batches(int $limit = 10): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT b.*, i.name, i.item_type
            FROM item_batches b
            JOIN directory_items i ON i.id = b.item_id
            ORDER BY b.updated_at DESC, b.id DESC
            LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll();
}
 
/**
 * Save one batch. If the item already has a batch with this number, the
 * quantity is added to it (restock); otherwise a new batch row is created.
 *
 * @param  array $b  batch_number, manufacturing_date, expiration_date, quantity
 * @return string    'created' | 'updated'
 */
function batch_save(PDO $pdo, int $itemId, array $b): string
{
    $find = $pdo->prepare('SELECT id FROM item_batches WHERE item_id = ? AND batch_number = ?');
    $find->execute([$itemId, $b['batch_number']]);
    $existingId = $find->fetchColumn();
 
    if ($existingId) {
        $pdo->prepare('UPDATE item_batches SET quantity = quantity + ? WHERE id = ?')
            ->execute([$b['quantity'], $existingId]);
        return 'updated';
    }
 
    $pdo->prepare('INSERT INTO item_batches (item_id, batch_number, manufacturing_date, expiration_date, quantity)
                   VALUES (?, ?, ?, ?, ?)')
        ->execute([$itemId, $b['batch_number'], $b['manufacturing_date'], $b['expiration_date'], $b['quantity']]);
    return 'created';
}
 
/**
 * Recalculate an item's totals from its batches:
 *   quantity        = sum of all batch quantities
 *   expiration_date = soonest expiry among batches that still have stock
 * Items with no batches keep whatever was typed in manually.
 */
function directory_recalc_item(PDO $pdo, int $itemId): void
{
    $count = $pdo->prepare('SELECT COUNT(*) FROM item_batches WHERE item_id = ?');
    $count->execute([$itemId]);
    if ((int) $count->fetchColumn() === 0) {
        return;
    }
 
    $pdo->prepare(
        'UPDATE directory_items SET
            quantity = (SELECT COALESCE(SUM(quantity), 0) FROM item_batches WHERE item_id = ?),
            expiration_date = (SELECT MIN(expiration_date) FROM item_batches WHERE item_id = ? AND quantity > 0)
         WHERE id = ?'
    )->execute([$itemId, $itemId, $itemId]);
}
 
/**
 * Barcode "Add" form: validate, then save a batch.
 * Known barcode  -> the batch is added to that item.
 * Unknown barcode -> the item is created first (needs type + name).
 *
 * Expects keys: barcode, batch, mfg, exp, qty (+ type, name, dosage for new items).
 *
 * @param  array $errors  filled with human-readable messages
 * @return array|null     ['message' => ..., 'item_id' => ...] or null on error
 */
function barcode_add_batch(PDO $pdo, array $in, array &$errors): ?array
{
    $errors = [];
    $get = fn(string $k): string => trim((string) ($in[$k] ?? ''));
 
    // ---- barcode / existing item ----
    $barcode   = $get('barcode');
    $barcodeOk = false;
    $item      = null;
 
    if ($barcode === '') {
        $errors[] = 'Barcode is required.';
    } elseif (strlen($barcode) > 64 || !preg_match('/^[\x20-\x7E]+$/', $barcode)) {
        $errors[] = 'Barcode must be 64 characters or less and use normal letters, numbers or symbols.';
    } else {
        $barcodeOk = true;
        $item = get_item_by_barcode($barcode);
        if ($item && $item['status'] === 'archived') {
            $errors[] = 'This item is archived. Restore it in the Directory first.';
        }
    }
 
    // ---- new item details (only when the barcode is new) ----
    $isNew    = $barcodeOk && !$item;
    $itemData = null;
    if ($isNew) {
        $type = $get('type');
        if (!in_array($type, ['product', 'supply'], true)) {
            $errors[] = 'Please choose Product or Supply.';
        } else {
            $itemErrors = [];
            $itemData = directory_validate([
                'item_type' => $type,
                'name'      => $get('name'),
                'dosage'    => $get('dosage'),
                'barcode'   => $barcode,
                'quantity'  => '0',
            ], $itemErrors);
            $errors = array_merge($errors, $itemErrors);
        }
    }
 
    // ---- batch fields ----
    $batchNo = $get('batch');
    if ($batchNo === '') {
        $errors[] = 'Batch number is required.';
    } elseif (mb_strlen($batchNo) > 50) {
        $errors[] = 'Batch number must be 50 characters or less.';
    }
 
    $mfg = $get('mfg');
    $exp = $get('exp');
    $mfgOk = $expOk = false;
 
    if ($mfg === '') {
        $errors[] = 'Manufacturing date is required.';
    } elseif (!is_valid_ymd($mfg)) {
        $errors[] = 'Manufacturing date is not a valid date.';
    } elseif ($mfg > date('Y-m-d')) {
        $errors[] = 'Manufacturing date cannot be in the future.';
    } else {
        $mfgOk = true;
    }
 
    if ($exp === '') {
        $errors[] = 'Expiry date is required.';
    } elseif (!is_valid_ymd($exp)) {
        $errors[] = 'Expiry date is not a valid date.';
    } else {
        $expOk = true;
    }
    if ($mfgOk && $expOk && $exp <= $mfg) {
        $errors[] = 'Expiry date must be after the manufacturing date.';
    }
 
    $qtyRaw = $get('qty');
    $qty = 0;
    if ($qtyRaw === '') {
        $errors[] = 'Quantity is required.';
    } elseif (filter_var($qtyRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]) === false) {
        $errors[] = 'Quantity must be a whole number from 0 to 100,000.';
    } else {
        $qty = (int) $qtyRaw;
    }
 
    if ($errors) {
        return null;
    }
 
    // ---- save (all or nothing) ----
    try {
        $pdo->beginTransaction();
 
        if ($isNew) {
            $itemId = (int) directory_save($pdo, $itemData);
            $name   = $itemData['name'];
        } else {
            $itemId = (int) $item['id'];
            $name   = $item['name'];
        }
 
        $how = batch_save($pdo, $itemId, [
            'batch_number'       => $batchNo,
            'manufacturing_date' => $mfg,
            'expiration_date'    => $exp,
            'quantity'           => $qty,
        ]);
        directory_recalc_item($pdo, $itemId);
 
        $total = $pdo->prepare('SELECT quantity FROM directory_items WHERE id = ?');
        $total->execute([$itemId]);
        $totalQty = (int) $total->fetchColumn();
 
        $pdo->commit();
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $e->getMessage();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Could not save: ' . $e->getMessage();
        return null;
    }
 
    $message = ($isNew ? 'Created ' . $name . '. ' : '')
             . ($how === 'created' ? 'Added batch ' : 'Added stock to batch ') . $batchNo
             . ' (' . $qty . ')' . ($isNew ? '' : ' to ' . $name)
             . '. Total in stock: ' . $totalQty . '.';
 
    return ['message' => $message, 'item_id' => $itemId];
}