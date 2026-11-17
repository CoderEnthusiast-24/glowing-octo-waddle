<?php
/**
 * DermAve IMS - data layer (v2 database: dermave_ims_v2).
 *
 * Tables used here:
 *   items (products + supplies)  brands  categories  units  item_barcodes
 *   item_batches                 machines  maintenance_logs
 * Views used here:
 *   v_item_stock  v_low_stock  v_out_of_stock  v_expiring_batches  v_maintenance_due
 *
 * The getters still return the SAME row shapes the pages already expect, so
 * products.php / machines.php / supplies.php / stock.php / dashboard.php keep
 * working. Two things to remember:
 *
 *   - Products/supplies and machines are now in different tables, so their ids
 *     can be the same number. Every row therefore also has a 'key' such as
 *     "product:11" or "machine:3" that is unique across both.
 *   - Stock quantity is never typed in directly any more. It comes from batches
 *     (or items.qty_on_hand) and changes only through stock_move()
 *     (includes/stock.php), which logs every change.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stock.php';

/* ------------------------------------------------------------------
 * Status helpers
 * ----------------------------------------------------------------*/

/**
 * Product / batch status from expiry date.
 * A missing date counts as OK (nothing to warn about).
 *
 * @return array [css class, label, days left]
 */
function product_status(?string $expiry): array
{
    if ($expiry === null || $expiry === '') {
        return ['ok', 'OK', null];
    }

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
 * Machine maintenance status. A machine with no maintenance record yet
 * (no due date) is shown as overdue / "Never serviced".
 *
 * @return array [css class, label, days left]
 */
function machine_status(?string $date): array
{
    if ($date === null || $date === '') {
        return ['overdue', 'Never serviced', null];
    }
    [$class, $label, $days] = product_status($date);
    return $class === 'expired' ? ['overdue', 'Overdue', $days] : [$class, $label, $days];
}

/**
 * Stock level.
 * @return string 'out' | 'low' | 'ok'
 */
function supply_status(int $stock, int $min): string
{
    if ($stock <= 0) {
        return 'out';
    }
    return $stock <= $min ? 'low' : 'ok';
}

/** True if $v is a real calendar date written as YYYY-MM-DD. */
function is_valid_ymd(string $v): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

/** Format a date for the tables, or an em dash when empty. */
function table_date(?string $d): string
{
    $ts = $d ? strtotime($d) : false;
    return $ts ? date('M d, Y', $ts) : '—';
}

/* ------------------------------------------------------------------
 * Reading the directory
 * ----------------------------------------------------------------*/

/**
 * Fetch directory items: products + supplies (items table) and machines.
 *
 * Every row has the old keys: id, item_type, name, brand, category,
 * subcategory, description, dosage, unit, quantity, min_stock, price,
 * batch_number, manufacturing_date, expiration_date, last_maintenance,
 * next_maintenance, status, notes ... plus:
 *   key           "product:11" (unique across items and machines)
 *   display_name  name, with the unit label for machines ("Scrubber (Unit 2)")
 *   barcode       primary barcode (products / supplies)
 *   unit_label, model, serial_number   (machines)
 *
 * For products/supplies: quantity = total of active batches (or items.qty_on_hand),
 * expiration_date = soonest expiry among batches that still have stock, and
 * batch_number / manufacturing_date = that same soonest-expiring batch.
 * For machines: last/next maintenance come from the latest maintenance_logs row.
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
    if (!$pdo) {
        return [];
    }

    $rows = [];

    // ----- products + supplies -----
    if ($type === null || $type === 'product' || $type === 'supply') {
        $sql = "SELECT i.id, i.item_type, i.name, i.dosage, i.description, i.min_stock, i.price,
                       i.status, i.notes, i.tracks_batches,
                       br.name AS brand,
                       COALESCE(p.name, c.name) AS category,
                       CASE WHEN p.id IS NULL THEN NULL ELSE c.name END AS subcategory,
                       un.name AS unit,
                       bc.barcode,
                       COALESCE(s.total_qty, 0) AS quantity,
                       s.next_expiry AS expiration_date,
                       (SELECT b2.batch_number FROM item_batches b2
                         WHERE b2.item_id = i.id AND b2.status = 'active' AND b2.qty_on_hand > 0
                         ORDER BY (b2.expiry_date IS NULL), b2.expiry_date, b2.id LIMIT 1) AS batch_number,
                       (SELECT b3.mfg_date FROM item_batches b3
                         WHERE b3.item_id = i.id AND b3.status = 'active' AND b3.qty_on_hand > 0
                         ORDER BY (b3.expiry_date IS NULL), b3.expiry_date, b3.id LIMIT 1) AS manufacturing_date
                  FROM items i
                  LEFT JOIN brands br        ON br.id = i.brand_id
                  LEFT JOIN categories c     ON c.id  = i.category_id
                  LEFT JOIN categories p     ON p.id  = c.parent_id
                  LEFT JOIN units un         ON un.id = i.unit_id
                  LEFT JOIN item_barcodes bc ON bc.item_id = i.id AND bc.is_primary = 1
                  LEFT JOIN v_item_stock s   ON s.item_id = i.id
                 WHERE 1 = 1";
        $params = [];
        if ($type) {
            $sql .= ' AND i.item_type = ?';
            $params[] = $type;
        }
        if ($status) {
            $sql .= ' AND i.status = ?';
            $params[] = $status;
        } elseif (!$includeArchived) {
            $sql .= " AND i.status = 'active'";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll() as $r) {
            $r['quantity']     = (int) $r['quantity'];
            $r['key']          = $r['item_type'] . ':' . $r['id'];
            $r['display_name'] = $r['name'];
            $r['unit_label'] = $r['model'] = $r['serial_number'] = null;
            $r['last_maintenance'] = $r['next_maintenance'] = null;
            $rows[] = $r;
        }
    }

    // ----- machines -----
    if ($type === null || $type === 'machine') {
        $sql = "SELECT m.id, m.name, m.unit_label, m.model, m.serial_number, m.description,
                       m.status, m.notes,
                       br.name AS brand,
                       COALESCE(p.name, c.name) AS category,
                       CASE WHEN p.id IS NULL THEN NULL ELSE c.name END AS subcategory,
                       (SELECT l.performed_on FROM maintenance_logs l WHERE l.machine_id = m.id
                         ORDER BY l.performed_on DESC, l.id DESC LIMIT 1) AS last_maintenance,
                       (SELECT l.next_due_date FROM maintenance_logs l WHERE l.machine_id = m.id
                         ORDER BY l.performed_on DESC, l.id DESC LIMIT 1) AS next_maintenance
                  FROM machines m
                  LEFT JOIN brands br    ON br.id = m.brand_id
                  LEFT JOIN categories c ON c.id  = m.category_id
                  LEFT JOIN categories p ON p.id  = c.parent_id
                 WHERE 1 = 1";
        $params = [];
        if ($status) {
            $sql .= ' AND m.status = ?';
            $params[] = $status;
        } elseif (!$includeArchived) {
            $sql .= " AND m.status = 'active'";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll() as $r) {
            $r['item_type']          = 'machine';
            $r['key']                = 'machine:' . $r['id'];
            $r['display_name']       = $r['name'] . ($r['unit_label'] ? ' (' . $r['unit_label'] . ')' : '');
            $r['dosage']             = null;
            $r['unit']               = 'unit';
            $r['quantity']           = 1;
            $r['min_stock']          = null;
            $r['price']              = null;
            $r['barcode']            = null;
            $r['batch_number']       = null;
            $r['manufacturing_date'] = null;
            $r['expiration_date']    = null;
            $rows[] = $r;
        }
    }

    usort($rows, function ($a, $b) {
        return [$a['item_type'], strtolower($a['name']), (string) $a['unit_label']]
           <=> [$b['item_type'], strtolower($b['name']), (string) $b['unit_label']];
    });
    return $rows;
}

/**
 * Distinct values for a column (used by clickable header filters).
 * 'category' = top-level categories, 'subcategory' = child categories.
 */
function get_directory_distinct(string $column): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    switch ($column) {
        case 'category':
            $sql = "SELECT name FROM categories WHERE parent_id IS NULL AND status = 'active' ORDER BY name";
            break;
        case 'subcategory':
            $sql = "SELECT DISTINCT name FROM categories WHERE parent_id IS NOT NULL AND status = 'active' ORDER BY name";
            break;
        case 'brand':
            $sql = 'SELECT name FROM brands ORDER BY name';
            break;
        case 'unit':
            $sql = 'SELECT name FROM units ORDER BY name';
            break;
        case 'status':
            return ['active', 'archived'];
        case 'item_type':
            return ['product', 'machine', 'supply'];
        default:
            return [];
    }
    return array_column($pdo->query($sql)->fetchAll(), 'name');
}

/* ------------------------------------------------------------------
 * Getters for the Products / Machines / Supplies pages (same shape as before)
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
            'expiry' => $r['expiration_date'],            // null when no stock / no batch
            'price'  => $r['price'] !== null ? (float) $r['price'] : null,
            'qty'    => (int) $r['quantity'],
            'min'    => (int) ($r['min_stock'] ?? 0),
        ];
    }, $items);
}

function get_machines(): array
{
    $items = get_directory_items(['item_type' => 'machine']);
    return array_map(function ($r) {
        return [
            'name' => $r['display_name'],
            'last' => $r['last_maintenance'] ?? '',
            'date' => $r['next_maintenance'],             // null = never serviced
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

/* ------------------------------------------------------------------
 * Directory writes (Add / Edit / Archive) - need the MySQL database
 * ----------------------------------------------------------------*/

/**
 * Which form fields each item type actually uses. Anything else is ignored.
 *
 * quantity / batch_number / manufacturing_date / expiration_date are only used
 * when a NEW product or supply is created with opening stock; on Edit they are
 * ignored (use the Stock or Barcode page to change stock).
 */
function directory_type_fields(): array
{
    $common = ['name', 'brand', 'category', 'subcategory', 'notes'];
    $stock  = ['unit', 'barcode', 'min_stock', 'price', 'quantity', 'batch_number', 'manufacturing_date', 'expiration_date'];
    return [
        'product' => array_merge($common, $stock, ['dosage']),
        'supply'  => array_merge($common, $stock),
        'machine' => array_merge($common, ['unit_label', 'model', 'serial_number', 'last_maintenance', 'next_maintenance']),
    ];
}

/**
 * Check + clean submitted form data.
 *
 * Fields the form does not send at all (barcode, unit_label, model,
 * serial_number) are left out of the result, so saving never wipes them.
 *
 * @param  array $in      raw input (e.g. $_POST)
 * @param  array $errors  filled with human-readable messages
 * @return array          column => value, ready for directory_save()
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
        'name'          => ['Name', 255],
        'brand'         => ['Brand', 120],
        'category'      => ['Category', 150],
        'subcategory'   => ['Subcategory', 150],
        'dosage'        => ['Dosage', 120],
        'unit'          => ['Unit', 40],
        'batch_number'  => ['Batch number', 80],
        'barcode'       => ['Barcode', 64],
        'unit_label'    => ['Unit label', 60],
        'model'         => ['Model', 120],
        'serial_number' => ['Serial number', 120],
        'notes'         => ['Notes', 2000],
    ];
    // Only touched when the form actually sends them
    $optionalFields = ['barcode', 'unit_label', 'model', 'serial_number'];

    $data = ['item_type' => $type];

    foreach ($texts as $col => [$label, $max]) {
        if (in_array($col, $optionalFields, true) && !array_key_exists($col, $in)) {
            continue;
        }
        $v = $get($col);
        if (mb_strlen($v) > $max) {
            $errors[] = "$label must be $max characters or less.";
        }
        $data[$col] = $v === '' ? null : $v;
    }
    if ($data['name'] === null) {
        $errors[] = 'Name is required.';
    }

    if (isset($data['barcode']) && !preg_match('/^[\x20-\x7E]+$/', $data['barcode'])) {
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
        if (!is_valid_ymd($v)) {
            $errors[] = "$label is not a valid date.";
            $data[$col] = null;
        } else {
            $data[$col] = $v;
        }
    }

    // drop / blank columns that don't apply to this type
    $allowed = $map[$type];
    foreach (['brand', 'category', 'subcategory', 'dosage', 'unit', 'quantity', 'min_stock', 'price',
              'batch_number', 'manufacturing_date', 'expiration_date', 'last_maintenance',
              'next_maintenance', 'notes'] as $col) {
        if (!in_array($col, $allowed, true)) {
            $data[$col] = null;
        }
    }
    foreach ($optionalFields as $col) {
        if (!in_array($col, $allowed, true)) {
            unset($data[$col]);
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

/** id of the brand with this name (created if new), or null. */
function directory_brand_id(PDO $pdo, ?string $name): ?int
{
    if ($name === null) {
        return null;
    }
    $st = $pdo->prepare('SELECT id FROM brands WHERE name = ?');
    $st->execute([$name]);
    $id = $st->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO brands (name) VALUES (?)')->execute([$name]);
    return (int) $pdo->lastInsertId();
}

/** id of the unit with this name or abbreviation (created if new), or null. */
function directory_unit_id(PDO $pdo, ?string $name): ?int
{
    if ($name === null) {
        return null;
    }
    $st = $pdo->prepare('SELECT id FROM units WHERE name = ? OR abbreviation = ? ORDER BY id LIMIT 1');
    $st->execute([$name, $name]);
    $id = $st->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO units (name) VALUES (?)')->execute([$name]);
    return (int) $pdo->lastInsertId();
}

/** Find a category under $parentId (null = top level) or create it. */
function directory_find_or_create_category(PDO $pdo, ?int $parentId, string $name, string $appliesTo): int
{
    $st = $pdo->prepare('SELECT id FROM categories WHERE parent_id <=> ? AND name = ?');
    $st->execute([$parentId, $name]);
    $id = $st->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO categories (parent_id, name, applies_to) VALUES (?, ?, ?)')
        ->execute([$parentId, $name, $appliesTo]);
    return (int) $pdo->lastInsertId();
}

/**
 * categories.id for the typed category / subcategory (created when new).
 * Returns the sub-category's id when one is given, else the top-level one.
 */
function directory_category_id(PDO $pdo, ?string $category, ?string $sub, string $type): ?int
{
    if ($category === null && $sub === null) {
        return null;
    }
    if ($category === null) {      // only a subcategory was typed: use it as the category
        $category = $sub;
        $sub = null;
    }
    $top = directory_find_or_create_category($pdo, null, $category, $type);
    return $sub === null ? $top : directory_find_or_create_category($pdo, $top, $sub, $type);
}

/**
 * Insert a new item ($id = null) or update an existing one.
 *
 * Products / supplies go to `items` (brand, category and unit are looked up or
 * created; the barcode goes to `item_barcodes`). Machines go to `machines`, and
 * their maintenance dates become a `maintenance_logs` row.
 *
 * Quantity is NOT saved on Edit. On Add, a quantity above 0 becomes opening
 * stock through stock_move(). Batch-tracked items need a batch number and
 * expiration date for that.
 *
 * Runs in a transaction (or joins the caller's).
 *
 * @return int|null  the item id, or null if an update targeted a missing item
 * @throws InvalidArgumentException for problems the user can fix (duplicate barcode, ...)
 */
function directory_save(PDO $pdo, array $data, ?int $id = null, ?int $userId = null): ?int
{
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $result = $data['item_type'] === 'machine'
            ? directory_save_machine($pdo, $data, $id, $userId)
            : directory_save_item($pdo, $data, $id, $userId);
        if ($own) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Product / supply part of directory_save(). */
function directory_save_item(PDO $pdo, array $data, ?int $id, ?int $userId): ?int
{
    $type = $data['item_type'];

    // A barcode can only belong to one item
    if (!empty($data['barcode'])) {
        $dup = $pdo->prepare('SELECT item_id FROM item_barcodes WHERE barcode = ? AND item_id <> ?');
        $dup->execute([$data['barcode'], $id ?? 0]);
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException('That barcode is already used by another item.');
        }
    }

    $brandId    = directory_brand_id($pdo, $data['brand'] ?? null);
    $categoryId = directory_category_id($pdo, $data['category'] ?? null, $data['subcategory'] ?? null, $type);
    $unitId     = directory_unit_id($pdo, $data['unit'] ?? null);

    if ($id === null) {
        // Products always track batches + expiry. A supply does only when a batch number is given.
        $tracks = ($type === 'product' || !empty($data['batch_number'])) ? 1 : 0;
        $qty    = (int) ($data['quantity'] ?? 0);

        // Check the opening stock BEFORE inserting anything
        if ($qty > 0 && $tracks && (empty($data['batch_number']) || empty($data['expiration_date']))) {
            throw new InvalidArgumentException('Enter a batch number and expiration date for the opening stock (or leave Quantity at 0).');
        }

        $pdo->prepare(
            'INSERT INTO items (item_type, name, brand_id, category_id, unit_id, dosage, min_stock, price,
                                tracks_batches, tracks_expiry, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $type, $data['name'], $brandId, $categoryId, $unitId, $data['dosage'] ?? null,
            $data['min_stock'] ?? null, $data['price'] ?? null, $tracks, $tracks, $data['notes'] ?? null,
        ]);
        $itemId = (int) $pdo->lastInsertId();
        directory_set_barcode($pdo, $itemId, $data['barcode'] ?? null);

        if ($qty > 0) {
            $opts = $tracks ? ['batch' => [
                'batch_number' => $data['batch_number'],
                'mfg_date'     => $data['manufacturing_date'] ?? '',
                'expiry_date'  => $data['expiration_date'],
            ]] : [];
            $res = stock_move($pdo, $itemId, 'in', $qty, 'Opening balance', $userId, null, $opts);
            if (!$res['ok']) {
                throw new InvalidArgumentException($res['error']);
            }
        }
        return $itemId;
    }

    $exists = $pdo->prepare('SELECT 1 FROM items WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetchColumn()) {
        return null;
    }

    $pdo->prepare(
        'UPDATE items SET item_type = ?, name = ?, brand_id = ?, category_id = ?, unit_id = ?,
                dosage = ?, min_stock = ?, price = ?, notes = ?
          WHERE id = ?'
    )->execute([
        $type, $data['name'], $brandId, $categoryId, $unitId, $data['dosage'] ?? null,
        $data['min_stock'] ?? null, $data['price'] ?? null, $data['notes'] ?? null, $id,
    ]);
    directory_set_barcode($pdo, $id, $data['barcode'] ?? null);
    return $id;
}

/** Set an item's primary barcode. A blank / missing barcode leaves the current one alone. */
function directory_set_barcode(PDO $pdo, int $itemId, ?string $barcode): void
{
    if ($barcode === null || $barcode === '') {
        return;
    }
    $st = $pdo->prepare('SELECT id, barcode FROM item_barcodes WHERE item_id = ? AND is_primary = 1');
    $st->execute([$itemId]);
    $row = $st->fetch();

    if (!$row) {
        $pdo->prepare('INSERT INTO item_barcodes (item_id, barcode, pack_qty, is_primary) VALUES (?, ?, 1, 1)')
            ->execute([$itemId, $barcode]);
    } elseif ($row['barcode'] !== $barcode) {
        $pdo->prepare('UPDATE item_barcodes SET barcode = ? WHERE id = ?')->execute([$barcode, $row['id']]);
    }
}

/** Machine part of directory_save(). */
function directory_save_machine(PDO $pdo, array $data, ?int $id, ?int $userId): ?int
{
    // Fields the form did not send stay as they are
    $has = fn(string $k): bool => array_key_exists($k, $data);

    if ($has('serial_number') && $data['serial_number'] !== null) {
        $dup = $pdo->prepare('SELECT id FROM machines WHERE serial_number = ? AND id <> ?');
        $dup->execute([$data['serial_number'], $id ?? 0]);
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException('That serial number is already used by another machine.');
        }
    }
    if ($has('unit_label') && $data['unit_label'] !== null) {
        $dup = $pdo->prepare('SELECT id FROM machines WHERE name = ? AND unit_label = ? AND id <> ?');
        $dup->execute([$data['name'], $data['unit_label'], $id ?? 0]);
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException('There is already a machine with that name and unit label.');
        }
    }

    $brandId    = directory_brand_id($pdo, $data['brand'] ?? null);
    $categoryId = directory_category_id($pdo, $data['category'] ?? null, $data['subcategory'] ?? null, 'machine');

    $cols = ['name' => $data['name'], 'brand_id' => $brandId, 'category_id' => $categoryId, 'notes' => $data['notes'] ?? null];
    foreach (['unit_label', 'model', 'serial_number'] as $k) {
        if ($has($k)) {
            $cols[$k] = $data[$k];
        }
    }

    if ($id === null) {
        $pdo->prepare('INSERT INTO machines (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($cols));
        $machineId = (int) $pdo->lastInsertId();
    } else {
        $exists = $pdo->prepare('SELECT 1 FROM machines WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            return null;
        }
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($cols)));
        $pdo->prepare("UPDATE machines SET $set WHERE id = ?")->execute(array_merge(array_values($cols), [$id]));
        $machineId = $id;
    }

    directory_machine_maintenance($pdo, $machineId, $data['last_maintenance'] ?? null, $data['next_maintenance'] ?? null, $userId);
    return $machineId;
}

/**
 * Turn the Directory's "last / next maintenance" fields into maintenance_logs:
 *   - a new "last" date adds a new routine log (with the next due date)
 *   - the same "last" date with a different "next" updates the latest log's due date
 * Leaving both blank changes nothing.
 */
function directory_machine_maintenance(PDO $pdo, int $machineId, ?string $last, ?string $next, ?int $userId): void
{
    if ($last === null && $next === null) {
        return;
    }

    $st = $pdo->prepare('SELECT id, performed_on, next_due_date FROM maintenance_logs
                          WHERE machine_id = ? ORDER BY performed_on DESC, id DESC LIMIT 1');
    $st->execute([$machineId]);
    $latest = $st->fetch();

    if ($last !== null && (!$latest || $latest['performed_on'] !== $last)) {
        $pdo->prepare("INSERT INTO maintenance_logs (machine_id, performed_on, maintenance_type, performed_by, next_due_date, notes)
                       VALUES (?, ?, 'routine', ?, ?, 'Entered from the Directory page')")
            ->execute([$machineId, $last, $userId, $next]);
        return;
    }
    if (!$latest) {
        throw new InvalidArgumentException('Enter the last maintenance date too. A machine needs one maintenance record before a due date can be saved.');
    }
    if ($next !== $latest['next_due_date']) {
        $pdo->prepare('UPDATE maintenance_logs SET next_due_date = ? WHERE id = ?')->execute([$next, $latest['id']]);
    }
}

/**
 * Archive or restore items.
 *
 * Items and machines can share an id, so each entry is a key like "product:11",
 * "supply:35" or "machine:3" (the 'key' of a directory row). Anything else is ignored.
 *
 * @param  string[] $keys
 * @param  string   $status 'archived' | 'active'
 * @return int      rows changed
 */
function directory_set_status(PDO $pdo, array $keys, string $status): int
{
    if (!in_array($status, ['active', 'archived'], true)) {
        return 0;
    }

    $ids = ['items' => [], 'machines' => []];
    foreach ($keys as $key) {
        if (!is_string($key) || !preg_match('/^(product|supply|machine):(\d+)$/', $key, $m) || (int) $m[2] < 1) {
            continue;
        }
        $ids[$m[1] === 'machine' ? 'machines' : 'items'][(int) $m[2]] = (int) $m[2];
    }

    $changed = 0;
    foreach ($ids as $table => $list) {
        if (!$list) {
            continue;
        }
        $marks = implode(', ', array_fill(0, count($list), '?'));
        $stmt  = $pdo->prepare("UPDATE $table SET status = ? WHERE id IN ($marks)");
        $stmt->execute(array_merge([$status], array_values($list)));
        $changed += $stmt->rowCount();
    }
    return $changed;
}

/* ------------------------------------------------------------------
 * Dashboard numbers
 * ----------------------------------------------------------------*/

/**
 * Counts for the dashboard cards. Archived items are never counted.
 *
 *  products / machines / supplies : how many different items of each type
 *  low_stock       : items with some left, but at or below min_stock   (v_low_stock)
 *  out_of_stock    : items with none left                              (v_out_of_stock)
 *  expired         : BATCHES with stock that are past their expiry date
 *  expiring_30/60/90 : BATCHES with stock expiring in 0-30, 31-60, 61-90 days
 *  expiring_soon   : the three ranges added together
 *  maintenance_due : machines overdue, due within 30 days, or never serviced (v_maintenance_due)
 *  maintenance_overdue : machines already past their next maintenance date
 */
function dashboard_stats(): array
{
    $s = [
        'products' => 0, 'machines' => 0, 'supplies' => 0,
        'low_stock' => 0, 'out_of_stock' => 0,
        'expired' => 0, 'expiring_30' => 0, 'expiring_60' => 0, 'expiring_90' => 0, 'expiring_soon' => 0,
        'maintenance_due' => 0, 'maintenance_overdue' => 0,
    ];

    $pdo = db();
    if (!$pdo) {
        return $s;
    }

    $count = fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();

    $s['products']     = $count("SELECT COUNT(*) FROM items WHERE item_type = 'product' AND status = 'active'");
    $s['supplies']     = $count("SELECT COUNT(*) FROM items WHERE item_type = 'supply' AND status = 'active'");
    $s['machines']     = $count("SELECT COUNT(*) FROM machines WHERE status = 'active'");
    $s['low_stock']    = $count('SELECT COUNT(*) FROM v_low_stock');
    $s['out_of_stock'] = $count('SELECT COUNT(*) FROM v_out_of_stock');

    $buckets = ['expired' => 'expired', '0-30' => 'expiring_30', '31-60' => 'expiring_60', '61-90' => 'expiring_90'];
    foreach ($pdo->query('SELECT bucket, COUNT(*) AS n FROM v_expiring_batches GROUP BY bucket')->fetchAll() as $r) {
        if (isset($buckets[$r['bucket']])) {
            $s[$buckets[$r['bucket']]] = (int) $r['n'];
        }
    }
    $s['expiring_soon'] = $s['expiring_30'] + $s['expiring_60'] + $s['expiring_90'];

    foreach ($pdo->query('SELECT due_status, COUNT(*) AS n FROM v_maintenance_due GROUP BY due_status')->fetchAll() as $r) {
        $s['maintenance_due'] += (int) $r['n'];
        if ($r['due_status'] === 'overdue') {
            $s['maintenance_overdue'] += (int) $r['n'];
        }
    }

    return $s;
}

/* ------------------------------------------------------------------
 * Barcode + batches
 * ----------------------------------------------------------------*/

/**
 * Find a product or supply by barcode (archived ones included, so the
 * caller can tell the user). Returns null when not found / no database.
 * Row keys: id, item_type, name, dosage, status, tracks_batches, barcode,
 * pack_qty, unit, quantity (current total).
 */
function get_item_by_barcode(string $barcode): ?array
{
    $pdo = db();
    if (!$pdo || $barcode === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT i.id, i.item_type, i.name, i.dosage, i.status, i.tracks_batches, i.tracks_expiry, i.min_stock,
                bc.barcode, bc.pack_qty, un.name AS unit, COALESCE(s.total_qty, 0) AS quantity
           FROM item_barcodes bc
           JOIN items i             ON i.id = bc.item_id
           LEFT JOIN units un       ON un.id = i.unit_id
           LEFT JOIN v_item_stock s ON s.item_id = i.id
          WHERE bc.barcode = ?
          LIMIT 1'
    );
    $stmt->execute([$barcode]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['quantity'] = (int) $row['quantity'];
    return $row;
}

/**
 * All batches of one item, soonest expiry first.
 * Column names are aliased to the old ones: manufacturing_date,
 * expiration_date, quantity.
 */
function get_batches(int $itemId): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, item_id, batch_number, mfg_date AS manufacturing_date, expiry_date AS expiration_date,
                qty_on_hand AS quantity, status
           FROM item_batches
          WHERE item_id = ?
          ORDER BY (expiry_date IS NULL), expiry_date, batch_number'
    );
    $stmt->execute([$itemId]);
    return $stmt->fetchAll();
}

/** The most recently saved batches, with their item name and type (same aliases as get_batches). */
function get_recent_batches(int $limit = 10): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT b.id, b.item_id, b.batch_number, b.mfg_date AS manufacturing_date,
                   b.expiry_date AS expiration_date, b.qty_on_hand AS quantity, b.status,
                   i.name, i.item_type
              FROM item_batches b
              JOIN items i ON i.id = b.item_id
             ORDER BY b.updated_at DESC, b.id DESC
             LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll();
}

/**
 * Barcode "Add" form: validate, then add stock.
 * Known barcode   -> stock goes into a batch of that item.
 * Unknown barcode -> the item is created first (needs type + name).
 *
 * An existing batch number gets the quantity added; a new number creates the
 * batch. Every addition is logged in stock_movements (reason "Restock").
 * An existing item that does not track batches just gets the quantity added
 * (batch / dates are ignored).
 *
 * Expects keys: barcode, batch, mfg, exp, qty (+ type, name, dosage for new items).
 *
 * @param  array    $errors  filled with human-readable messages
 * @param  int|null $userId  the logged-in user (saved on the movement)
 * @return array|null        ['message' => ..., 'item_id' => ...] or null on error
 */
function barcode_add_batch(PDO $pdo, array $in, array &$errors, ?int $userId = null): ?array
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
    $batchNo  = $get('batch');
    if ($isNew) {
        $type = $get('type');
        if (!in_array($type, ['product', 'supply'], true)) {
            $errors[] = 'Please choose Product or Supply.';
        } else {
            $itemErrors = [];
            $itemData = directory_validate([
                'item_type'    => $type,
                'name'         => $get('name'),
                'dosage'       => $get('dosage'),
                'barcode'      => $barcode,
                'quantity'     => '0',
                'batch_number' => $batchNo,     // makes a new supply batch-tracked too
            ], $itemErrors);
            $errors = array_merge($errors, $itemErrors);
        }
    }

    // ---- batch fields (not needed for an existing item that does not track batches) ----
    $needsBatch = $isNew || ($item && (int) $item['tracks_batches'] === 1);
    $mfg = $get('mfg');
    $exp = $get('exp');

    if ($needsBatch) {
        if ($batchNo === '') {
            $errors[] = 'Batch number is required.';
        } elseif (mb_strlen($batchNo) > 50) {
            $errors[] = 'Batch number must be 50 characters or less.';
        }

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
    }

    $qtyRaw = $get('qty');
    $qty = 0;
    if ($qtyRaw === '') {
        $errors[] = 'Quantity is required.';
    } elseif (filter_var($qtyRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) === false) {
        $errors[] = 'Quantity must be a whole number from 1 to 100,000.';
    } else {
        $qty = (int) $qtyRaw;
    }

    if ($errors) {
        return null;
    }

    // ---- save (all or nothing) ----
    $own = !$pdo->inTransaction();
    try {
        if ($own) {
            $pdo->beginTransaction();
        }

        if ($isNew) {
            $itemId = (int) directory_save($pdo, $itemData, null, $userId);
            $name   = $itemData['name'];
        } else {
            $itemId = (int) $item['id'];
            $name   = $item['name'];
        }

        $opts = [];
        $how  = 'stock';
        if ($needsBatch) {
            $exists = $pdo->prepare('SELECT 1 FROM item_batches WHERE item_id = ? AND batch_number = ?');
            $exists->execute([$itemId, $batchNo]);
            $how  = $exists->fetchColumn() ? 'updated' : 'created';
            $opts = ['batch' => ['batch_number' => $batchNo, 'mfg_date' => $mfg, 'expiry_date' => $exp]];
        }

        $res = stock_move($pdo, $itemId, 'in', $qty, 'Restock', $userId, null, $opts);
        if (!$res['ok']) {
            throw new InvalidArgumentException($res['error']);
        }
        $totalQty = (int) $res['after'];

        if ($own) {
            $pdo->commit();
        }
    } catch (InvalidArgumentException $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $e->getMessage();
        return null;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Could not save: ' . $e->getMessage();
        return null;
    }

    if ($how === 'stock') {
        $what = 'Added ' . $qty;
    } else {
        $what = ($how === 'created' ? 'Added batch ' : 'Added stock to batch ') . $batchNo . ' (' . $qty . ')';
    }
    $message = ($isNew ? 'Created ' . $name . '. ' : '')
             . $what . ($isNew ? '' : ' to ' . $name)
             . '. Total in stock: ' . $totalQty . '.';

    return ['message' => $message, 'item_id' => $itemId];
}