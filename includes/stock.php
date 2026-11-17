<?php
/**
 * DermAve IMS - stock in / stock out (v2 database).
 *
 * stock_move() is the ONE place that changes stock and logs it.
 * The Stock page, the Barcode page, the Directory (opening balances) and
 * (later) treatments all call it, so the rules live here and nowhere else.
 *
 * How stock is stored in v2:
 *   - items with tracks_batches = 1 (all products): stock lives in
 *     item_batches.qty_on_hand. The item total is the sum of its ACTIVE batches
 *     (this is what the v_item_stock view calculates).
 *   - items with tracks_batches = 0 (most supplies): stock lives in
 *     items.qty_on_hand.
 *   - every change writes one or more stock_movements rows with a SIGNED
 *     quantity_change (+ for in, - for out) and a reason_id from movement_reasons.
 */

require_once __DIR__ . '/db.php';

/** Treatments must never use expired stock (see stock_move()). */
const STOCK_TREATMENT_REASON = 'Used in treatment';

/**
 * Add or remove stock for one item and write it to stock_movements.
 *
 * @param PDO      $pdo             database connection (from db())
 * @param int      $itemId          items.id
 * @param string   $type            'in' or 'out'
 * @param int      $qty             how many units (a positive whole number)
 * @param string   $reason          a movement_reasons name (e.g. 'Restock', 'Damaged')
 *                                  or a movement_reasons id
 * @param int|null $userId          who is doing it (the logged-in user's id)
 * @param int|null $treatmentLogId  set only when a treatment caused the movement
 * @param array    $opts            optional:
 *    'batch_id' => int      use this exact batch (in or out)
 *    'batch'    => array    stock IN to a batch by number: batch_number (required),
 *                           mfg_date, expiry_date, unit_cost, supplier_id.
 *                           An existing batch number gets the quantity added;
 *                           a new number creates the batch.
 *    'notes'    => string   free text saved on the movement (255 chars max)
 *
 * Batch items:
 *   IN  needs 'batch_id' or 'batch'.
 *   OUT without 'batch_id' takes from the batches that expire first (FEFO),
 *       splitting across batches when needed (one movement row per batch).
 *       For 'Used in treatment' (or any treatment_log_id) expired batches are skipped.
 *
 * @return array ['ok' => true,  'item' => name, 'before' => n, 'after' => n, 'moves' => n]
 *            or ['ok' => false, 'error' => 'what went wrong']
 *
 * Works on its own, and also inside a bigger transaction: if one is already
 * open it uses that one and leaves the commit / rollback to the caller (the
 * caller MUST roll back when ok is false, because earlier steps may have run).
 */
function stock_move(PDO $pdo, int $itemId, string $type, int $qty, string $reason,
                    ?int $userId = null, ?int $treatmentLogId = null, array $opts = []): array
{
    // 1. Check the input
    if ($type !== 'in' && $type !== 'out') {
        return ['ok' => false, 'error' => 'Movement type must be "in" or "out".'];
    }
    if ($qty < 1) {
        return ['ok' => false, 'error' => 'Quantity must be at least 1.'];
    }
    $reason = trim($reason);
    if ($reason === '') {
        return ['ok' => false, 'error' => 'Please give a reason.'];
    }
    $notes = trim((string) ($opts['notes'] ?? ''));
    if (mb_strlen($notes) > 255) {
        return ['ok' => false, 'error' => 'Notes are too long (255 characters at most).'];
    }
    $notes = $notes === '' ? null : $notes;

    // Use the caller's transaction if there is one, otherwise start our own
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // 2. Check the reason
        $why = stock_resolve_reason($pdo, $reason, $type);
        if (!$why) {
            return stock_fail($pdo, $ownTransaction, 'Unknown reason "' . $reason . '". Please pick one from the list.');
        }

        // 3. Check the item (locked until we finish, so two people cannot clash)
        $find = $pdo->prepare('SELECT id, name, status, tracks_batches, qty_on_hand FROM items WHERE id = ? FOR UPDATE');
        $find->execute([$itemId]);
        $item = $find->fetch();

        if (!$item) {
            return stock_fail($pdo, $ownTransaction, 'That item no longer exists.');
        }
        if ($item['status'] !== 'active') {
            return stock_fail($pdo, $ownTransaction, $item['name'] . ' is archived. Restore it first.');
        }

        $tracksBatches = (int) $item['tracks_batches'] === 1;
        $before = stock_item_total($pdo, $item);

        // 4. Work out what changes: a list of [batch_id|null, signed change, batch balance after|null]
        if (!$tracksBatches) {
            if ($type === 'in') {
                $pdo->prepare('UPDATE items SET qty_on_hand = qty_on_hand + ? WHERE id = ?')
                    ->execute([$qty, $itemId]);
            } else {
                // The WHERE makes the database itself refuse to go below zero
                $upd = $pdo->prepare('UPDATE items SET qty_on_hand = qty_on_hand - ? WHERE id = ? AND qty_on_hand >= ?');
                $upd->execute([$qty, $itemId, $qty]);
                if ($upd->rowCount() === 0) {
                    return stock_fail($pdo, $ownTransaction,
                        'Not enough stock for ' . $item['name'] . ' (' . $before . ' left, ' . $qty . ' needed).');
                }
            }
            $moves = [[null, $type === 'in' ? $qty : -$qty, null]];
        } elseif ($type === 'in') {
            $res = stock_batch_in($pdo, $item, $qty, $opts);
            if (isset($res['error'])) {
                return stock_fail($pdo, $ownTransaction, $res['error']);
            }
            $moves = $res['moves'];
        } else {
            $skipExpired = ($why['name'] === STOCK_TREATMENT_REASON || $treatmentLogId !== null);
            $res = stock_batch_out($pdo, $item, $qty, $opts, $skipExpired);
            if (isset($res['error'])) {
                return stock_fail($pdo, $ownTransaction, $res['error']);
            }
            $moves = $res['moves'];
        }

        // 5. Log every movement, keeping a running item balance
        $log = $pdo->prepare(
            'INSERT INTO stock_movements
               (item_id, batch_id, movement_type, quantity_change, batch_balance_after,
                item_balance_after, reason_id, user_id, treatment_log_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $running = $before;
        foreach ($moves as [$batchId, $change, $batchBalance]) {
            $running += $change;
            $log->execute([$itemId, $batchId, $type, $change, $batchBalance, $running,
                           (int) $why['id'], $userId, $treatmentLogId, $notes]);
        }

        if ($ownTransaction) {
            $pdo->commit();
        }
        return ['ok' => true, 'item' => $item['name'], 'before' => $before, 'after' => $running, 'moves' => count($moves)];

    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;   // let the page / API show the database error
    }
}

/** Helper: undo our own transaction (if we started it) and return an error. */
function stock_fail(PDO $pdo, bool $ownTransaction, string $message): array
{
    if ($ownTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    return ['ok' => false, 'error' => $message];
}

/** True if $v is a real calendar date written as YYYY-MM-DD. */
function stock_valid_date(string $v): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

/**
 * Find the movement_reasons row for a name (or numeric id).
 * A reason whose direction matches the movement wins; 'adjust' reasons
 * (e.g. Stock count correction) can be used for both in and out.
 *
 * @return array|null  id, name, direction
 */
function stock_resolve_reason(PDO $pdo, string $reason, string $type): ?array
{
    if (ctype_digit($reason)) {
        $st = $pdo->prepare('SELECT id, name, direction FROM movement_reasons WHERE id = ? AND is_active = 1');
    } else {
        $st = $pdo->prepare('SELECT id, name, direction FROM movement_reasons WHERE name = ? AND is_active = 1');
    }
    $st->execute([ctype_digit($reason) ? (int) $reason : $reason]);
    $rows = $st->fetchAll();

    foreach ($rows as $r) {
        if ($r['direction'] === $type) {
            return $r;
        }
    }
    foreach ($rows as $r) {
        if ($r['direction'] === 'adjust') {
            return $r;
        }
    }
    return null;
}

/**
 * Reasons to offer in the Stock page, for one direction ('in' or 'out').
 * 'adjust' reasons appear in both lists.
 *
 * @return array rows with id, name, direction
 */
function get_movement_reasons(PDO $pdo, string $direction): array
{
    $st = $pdo->prepare(
        "SELECT id, name, direction FROM movement_reasons
          WHERE is_active = 1 AND direction IN (?, 'adjust')
          ORDER BY (direction = 'adjust'), id"
    );
    $st->execute([$direction]);
    return $st->fetchAll();
}

/** An item's current total: sum of its active batches, or items.qty_on_hand. */
function stock_item_total(PDO $pdo, array $item): int
{
    if ((int) $item['tracks_batches'] !== 1) {
        return (int) $item['qty_on_hand'];
    }
    $st = $pdo->prepare("SELECT COALESCE(SUM(qty_on_hand), 0) FROM item_batches WHERE item_id = ? AND status = 'active'");
    $st->execute([(int) $item['id']]);
    return (int) $st->fetchColumn();
}

/**
 * Stock IN for a batch-tracked item. Returns ['moves' => [...]] or ['error' => '...'].
 */
function stock_batch_in(PDO $pdo, array $item, int $qty, array $opts): array
{
    $itemId  = (int) $item['id'];
    $batchId = (int) ($opts['batch_id'] ?? 0);

    // A. a batch that was picked by id
    if ($batchId > 0) {
        $st = $pdo->prepare('SELECT id, batch_number, qty_on_hand, status FROM item_batches WHERE id = ? AND item_id = ? FOR UPDATE');
        $st->execute([$batchId, $itemId]);
        $batch = $st->fetch();
        if (!$batch) {
            return ['error' => 'That batch does not belong to ' . $item['name'] . '.'];
        }
        return stock_batch_add($pdo, $batch, $qty);
    }

    // B. a batch typed by number (existing -> add to it, new -> create it)
    $b  = is_array($opts['batch'] ?? null) ? $opts['batch'] : [];
    $no = trim((string) ($b['batch_number'] ?? ''));
    if ($no === '') {
        return ['error' => $item['name'] . ' is tracked by batch. Choose a batch, or enter a new batch number and expiry date.'];
    }
    if (mb_strlen($no) > 80) {
        return ['error' => 'Batch number must be 80 characters or less.'];
    }

    $st = $pdo->prepare('SELECT id, batch_number, qty_on_hand, status FROM item_batches WHERE item_id = ? AND batch_number = ? FOR UPDATE');
    $st->execute([$itemId, $no]);
    $batch = $st->fetch();
    if ($batch) {
        return stock_batch_add($pdo, $batch, $qty);
    }

    // New batch
    $mfg = trim((string) ($b['mfg_date'] ?? ''));
    $exp = trim((string) ($b['expiry_date'] ?? ''));
    if ($exp === '') {
        return ['error' => 'A new batch needs an expiry date.'];
    }
    if (!stock_valid_date($exp)) {
        return ['error' => 'Expiry date is not a valid date.'];
    }
    if ($mfg !== '' && !stock_valid_date($mfg)) {
        return ['error' => 'Manufacturing date is not a valid date.'];
    }
    if ($mfg !== '' && $exp <= $mfg) {
        return ['error' => 'Expiry date must be after the manufacturing date.'];
    }

    $pdo->prepare(
        "INSERT INTO item_batches (item_id, batch_number, supplier_id, mfg_date, expiry_date, received_date, unit_cost, qty_on_hand, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
    )->execute([
        $itemId, $no,
        ($b['supplier_id'] ?? null) ?: null,
        $mfg === '' ? null : $mfg,
        $exp,
        date('Y-m-d'),
        ($b['unit_cost'] ?? null) === '' ? null : ($b['unit_cost'] ?? null),
        $qty,
    ]);
    return ['moves' => [[(int) $pdo->lastInsertId(), $qty, $qty]]];
}

/** Add $qty to an existing (already locked) batch row. */
function stock_batch_add(PDO $pdo, array $batch, int $qty): array
{
    if ($batch['status'] !== 'active') {
        return ['error' => 'Batch ' . $batch['batch_number'] . ' is ' . $batch['status'] . ' and cannot receive stock.'];
    }
    $new = (int) $batch['qty_on_hand'] + $qty;
    $pdo->prepare('UPDATE item_batches SET qty_on_hand = ? WHERE id = ?')->execute([$new, (int) $batch['id']]);
    return ['moves' => [[(int) $batch['id'], $qty, $new]]];
}

/**
 * Stock OUT for a batch-tracked item. Returns ['moves' => [...]] or ['error' => '...'].
 * Without 'batch_id', takes from the soonest-expiring batches first (FEFO).
 */
function stock_batch_out(PDO $pdo, array $item, int $qty, array $opts, bool $skipExpired): array
{
    $itemId  = (int) $item['id'];
    $batchId = (int) ($opts['batch_id'] ?? 0);

    // A. one specific batch
    if ($batchId > 0) {
        $st = $pdo->prepare('SELECT id, batch_number, qty_on_hand, status FROM item_batches WHERE id = ? AND item_id = ? FOR UPDATE');
        $st->execute([$batchId, $itemId]);
        $batch = $st->fetch();
        if (!$batch) {
            return ['error' => 'That batch does not belong to ' . $item['name'] . '.'];
        }
        if ($batch['status'] !== 'active') {
            return ['error' => 'Batch ' . $batch['batch_number'] . ' is ' . $batch['status'] . '.'];
        }
        if ((int) $batch['qty_on_hand'] < $qty) {
            return ['error' => 'Not enough stock in batch ' . $batch['batch_number'] . ' (' . (int) $batch['qty_on_hand'] . ' left, ' . $qty . ' needed).'];
        }
        $new = (int) $batch['qty_on_hand'] - $qty;
        $pdo->prepare('UPDATE item_batches SET qty_on_hand = ? WHERE id = ?')->execute([$new, (int) $batch['id']]);
        return ['moves' => [[(int) $batch['id'], -$qty, $new]]];
    }

    // B. first-expiring-first-out across the item's batches
    $sql = "SELECT id, batch_number, qty_on_hand FROM item_batches
             WHERE item_id = ? AND status = 'active' AND qty_on_hand > 0"
         . ($skipExpired ? ' AND (expiry_date IS NULL OR expiry_date >= CURDATE())' : '')
         . ' ORDER BY (expiry_date IS NULL), expiry_date, id FOR UPDATE';
    $st = $pdo->prepare($sql);
    $st->execute([$itemId]);
    $batches = $st->fetchAll();

    $available = array_sum(array_map(fn($b) => (int) $b['qty_on_hand'], $batches));
    if ($available < $qty) {
        return ['error' => 'Not enough stock for ' . $item['name'] . ' (' . $available . ' left, ' . $qty . ' needed)'
                . ($skipExpired ? '. Expired batches are not used for treatments.' : '.')];
    }

    $upd   = $pdo->prepare('UPDATE item_batches SET qty_on_hand = ? WHERE id = ?');
    $moves = [];
    $need  = $qty;
    foreach ($batches as $b) {
        if ($need <= 0) {
            break;
        }
        $take = min($need, (int) $b['qty_on_hand']);
        $new  = (int) $b['qty_on_hand'] - $take;
        $upd->execute([$new, (int) $b['id']]);
        $moves[] = [(int) $b['id'], -$take, $new];
        $need   -= $take;
    }
    return ['moves' => $moves];
}

/**
 * The most recent stock movements, newest first (for the Stock page).
 *
 * Column names match the old page: quantity (always positive), balance_after
 * (the item's total after), reason (the reason name). New: batch_number and
 * quantity_change (signed).
 *
 * @return array rows with item name / type and the name of the user who did it
 */
function get_recent_movements(PDO $pdo, int $limit = 25): array
{
    $limit = max(1, min(200, $limit));
    $sql = 'SELECT m.id, m.item_id, m.batch_id, m.movement_type,
                   ABS(m.quantity_change) AS quantity, m.quantity_change,
                   m.item_balance_after AS balance_after, m.batch_balance_after,
                   r.name AS reason, m.notes, m.created_at,
                   i.name AS item_name, i.item_type, un.name AS unit,
                   b.batch_number, u.full_name AS user_name
              FROM stock_movements m
              JOIN items i             ON i.id  = m.item_id
              JOIN movement_reasons r  ON r.id  = m.reason_id
              LEFT JOIN item_batches b ON b.id  = m.batch_id
              LEFT JOIN units un       ON un.id = i.unit_id
              LEFT JOIN users u        ON u.id  = m.user_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll();
}