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
        'product' => array_merge($common, ['dosage', 'min_stock', 'price', 'batch_number', 'manufacturing_date', 'expiration_date']),
        'machine' => array_merge($common, ['last_maintenance', 'next_maintenance']),
        'supply'  => array_merge($common, ['min_stock', 'price', 'expiration_date']),
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
    foreach (['brand', 'category', 'subcategory', 'dosage', 'unit', 'quantity', 'min_stock', 'price',
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
 *
 * @return int|null  the item id, or null if an update targeted a missing item
 */
function directory_save(PDO $pdo, array $data, ?int $id = null): ?int
{
    $cols = ['item_type', 'name', 'brand', 'category', 'subcategory', 'dosage', 'unit', 'quantity',
             'min_stock', 'price', 'batch_number', 'manufacturing_date', 'expiration_date',
             'last_maintenance', 'next_maintenance', 'notes'];

    $params = [];
    foreach ($cols as $c) {
        $params[$c] = $data[$c] ?? null;
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
