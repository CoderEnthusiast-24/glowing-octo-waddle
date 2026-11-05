<?php
/**
 * DermAve IMS - stock in / stock out.
 *
 * stock_move() is the ONE place that changes an item's quantity and logs it.
 * The Directory buttons, the Barcode page and (later) treatments all call it,
 * so the rules live here and nowhere else.
 */

require_once __DIR__ . '/db.php';

/**
 * Add or remove stock for one item and write it to stock_movements.
 *
 * @param PDO         $pdo        database connection (from db())
 * @param int         $itemId     directory_items.id
 * @param string      $type       'in' or 'out'
 * @param int         $qty        how many units (a positive whole number)
 * @param string      $reason     e.g. 'Restock', 'Damaged', 'Used in treatment'
 * @param int|null    $userId     who is doing it (the logged-in user's id)
 * @param int|null    $treatmentLogId  set only when a treatment caused the movement
 *
 * @return array ['ok' => true,  'item' => name, 'before' => n, 'after' => n]
 *            or ['ok' => false, 'error' => 'what went wrong']
 *
 * Works on its own, and also inside a bigger transaction (a treatment that
 * deducts many items): if a transaction is already open it uses that one and
 * leaves the commit / rollback to the caller.
 */
function stock_move(PDO $pdo, int $itemId, string $type, int $qty, string $reason,
                    ?int $userId = null, ?int $treatmentLogId = null): array
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
    if (mb_strlen($reason) > 150) {
        return ['ok' => false, 'error' => 'Reason is too long (150 characters at most).'];
    }

    // Use the caller's transaction if there is one, otherwise start our own
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // 2. Check the item
        $find = $pdo->prepare('SELECT name, item_type, status FROM directory_items WHERE id = ?');
        $find->execute([$itemId]);
        $item = $find->fetch();

        if (!$item) {
            return stock_fail($pdo, $ownTransaction, 'That item no longer exists.');
        }
        if ($item['status'] !== 'active') {
            return stock_fail($pdo, $ownTransaction, $item['name'] . ' is archived. Restore it first.');
        }
        if ($item['item_type'] === 'machine') {
            return stock_fail($pdo, $ownTransaction, $item['name'] . ' is a machine. Only products and supplies can be moved in or out.');
        }

        // 3. Change the quantity.
        //    For "out" the WHERE quantity >= ? makes the database itself refuse
        //    to go below zero, even if two people click at the same moment.
        if ($type === 'in') {
            $upd = $pdo->prepare('UPDATE directory_items SET quantity = quantity + ? WHERE id = ?');
            $upd->execute([$qty, $itemId]);
        } else {
            $upd = $pdo->prepare('UPDATE directory_items SET quantity = quantity - ? WHERE id = ? AND quantity >= ?');
            $upd->execute([$qty, $itemId, $qty]);
            if ($upd->rowCount() === 0) {
                $have = $pdo->prepare('SELECT quantity FROM directory_items WHERE id = ?');
                $have->execute([$itemId]);
                return stock_fail($pdo, $ownTransaction,
                    'Not enough stock for ' . $item['name'] . ' (' . (int) $have->fetchColumn() . ' left, ' . $qty . ' needed).');
            }
        }

        // 4. Read the new quantity and log the movement
        $now = $pdo->prepare('SELECT quantity FROM directory_items WHERE id = ?');
        $now->execute([$itemId]);
        $after  = (int) $now->fetchColumn();
        $before = $type === 'in' ? $after - $qty : $after + $qty;

        $log = $pdo->prepare(
            'INSERT INTO stock_movements (item_id, movement_type, quantity, balance_after, reason, user_id, treatment_log_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $log->execute([$itemId, $type, $qty, $after, $reason, $userId, $treatmentLogId]);

        if ($ownTransaction) {
            $pdo->commit();
        }
        return ['ok' => true, 'item' => $item['name'], 'before' => $before, 'after' => $after];

    } catch (Exception $e) {
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

/**
 * The most recent stock movements, newest first (for the Stock page).
 *
 * @return array rows with item name / type and the name of the user who did it
 */
function get_recent_movements(PDO $pdo, int $limit = 25): array
{
    $limit = max(1, min(200, $limit));
    $sql = 'SELECT m.*, i.name AS item_name, i.item_type, i.unit, u.full_name AS user_name
            FROM stock_movements m
            JOIN directory_items i ON i.id = m.item_id
            LEFT JOIN users u ON u.id = m.user_id
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll();
}
