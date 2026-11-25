<?php
/** Ledger engine. Every movement of money goes through post_transaction(). */

function get_balance($userId)
{
    $st = db()->prepare('SELECT balance FROM users WHERE id = ?');
    $st->execute(array($userId));
    $r = $st->fetch();
    return $r ? (float)$r['balance'] : 0.0;
}

function set_balance($userId, $newBalance)
{
    db()->prepare('UPDATE users SET balance = ?, updated_at = ? WHERE id = ?')
        ->execute(array(round($newBalance, 2), now(), $userId));
}

/**
 * Record a transaction.
 * $direction: credit | debit
 * $opts: fee, status, channel, counterparty, ref, meta, date, category
 * Balance only moves when status is 'completed'.
 */
function post_transaction($userId, $direction, $category, $amount, $description, $opts = array())
{
    $amount = round((float)$amount, 2);
    $fee    = isset($opts['fee']) ? round((float)$opts['fee'], 2) : 0.0;
    $status = isset($opts['status']) ? $opts['status'] : 'completed';
    $ref    = isset($opts['ref']) ? $opts['ref'] : make_ref('TXN');
    $date   = isset($opts['date']) && $opts['date'] ? $opts['date'] : now();

    $balance = get_balance($userId);
    if ($status === 'completed') {
        $balance = ($direction === 'credit') ? $balance + $amount : $balance - ($amount + $fee);
        set_balance($userId, $balance);
    }

    db()->prepare('INSERT INTO transactions
        (user_id, ref, direction, category, amount, fee, balance_after, description, channel, counterparty, status, meta, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(array(
            $userId, $ref, $direction, $category, $amount, $fee, round($balance, 2),
            $description,
            isset($opts['channel']) ? $opts['channel'] : 'online',
            isset($opts['counterparty']) ? $opts['counterparty'] : '',
            $status,
            isset($opts['meta']) ? $opts['meta'] : null,
            $date
        ));

    return array('ref' => $ref, 'balance' => round($balance, 2), 'id' => db()->lastInsertId());
}

/**
 * Guarantee that the ledger entry for $ref ends up completed and the balance moved.
 *
 * settle_transaction() only acts on a pending row and returns false otherwise, which
 * meant an approval could mark a request approved while the money never moved. This
 * handles every state, and never credits or debits the same entry twice.
 */
function ensure_completed($ref, $userId, $direction, $category, $amount, $description, $opts = array())
{
    $tx = tx_by_ref($ref);

    // Nothing on the ledger yet — post it as a completed entry.
    if (!$tx) {
        $opts['ref'] = $ref;
        $opts['status'] = 'completed';
        post_transaction($userId, $direction, $category, $amount, $description, $opts);
        return 'created';
    }

    // Already settled. Do nothing, or it would move the money a second time.
    if ($tx['status'] === 'completed') return 'already';

    // Pending, declined or reversed — apply the movement now and mark it completed.
    $bal = get_balance($tx['user_id']);
    $bal = ($tx['direction'] === 'credit')
        ? $bal + (float)$tx['amount']
        : $bal - ((float)$tx['amount'] + (float)$tx['fee']);
    set_balance($tx['user_id'], $bal);
    db()->prepare("UPDATE transactions SET status = 'completed', balance_after = ? WHERE id = ?")
        ->execute(array(round($bal, 2), $tx['id']));

    return 'settled';
}

/** Flip a pending transaction to completed/declined and settle the balance. */
function settle_transaction($txId, $newStatus)
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
    $st->execute(array($txId));
    $tx = $st->fetch();
    if (!$tx || $tx['status'] !== 'pending') return false;

    if ($newStatus === 'completed') {
        $bal = get_balance($tx['user_id']);
        $bal = ($tx['direction'] === 'credit')
            ? $bal + (float)$tx['amount']
            : $bal - ((float)$tx['amount'] + (float)$tx['fee']);
        set_balance($tx['user_id'], $bal);
        $pdo->prepare('UPDATE transactions SET status = ?, balance_after = ? WHERE id = ?')
            ->execute(array('completed', round($bal, 2), $txId));
    } else {
        $pdo->prepare('UPDATE transactions SET status = ? WHERE id = ?')
            ->execute(array($newStatus, $txId));
    }
    return true;
}

function tx_by_ref($ref)
{
    $st = db()->prepare('SELECT * FROM transactions WHERE ref = ?');
    $st->execute(array($ref));
    return $st->fetch();
}

/** Admin credit / debit. Supports backdating. */
function admin_adjust($userId, $direction, $amount, $description, $date = null, $category = 'adjustment', $channel = 'branch')
{
    $r = post_transaction($userId, $direction, $category, $amount, $description, array(
        'status' => 'completed', 'date' => $date, 'channel' => $channel, 'ref' => make_ref($direction === 'credit' ? 'CRD' : 'DBT')
    ));
    audit($direction === 'credit' ? 'credit_account' : 'debit_account', 'user', $userId,
        money($amount) . ' — ' . $description);
    return $r;
}

/* ---------- fees & limits ---------- */

function transfer_fee($amount)
{
    $pct  = (float)setting('fee_percent', '0');
    $flat = (float)setting('fee_flat', '0');
    return round(($amount * $pct / 100) + $flat, 2);
}

/** Funds committed to entries that have not settled yet. */
function held_amount($userId)
{
    $st = db()->prepare("SELECT COALESCE(SUM(amount + fee),0) AS s FROM transactions
                         WHERE user_id = ? AND direction = 'debit' AND status = 'pending'");
    $st->execute(array($userId));
    $r = $st->fetch();
    return round((float)$r['s'], 2);
}

/** What the customer can actually spend right now. */
function available_balance($userId)
{
    return round(get_balance($userId) - held_amount($userId), 2);
}

function spent_today($userId)
{
    $st = db()->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM transactions
                         WHERE user_id = ? AND direction = 'debit' AND status IN ('completed','pending')
                         AND created_at >= ?");
    $st->execute(array($userId, date('Y-m-d') . ' 00:00:00'));
    $r = $st->fetch();
    return (float)$r['s'];
}

/* ---------- transfers ---------- */

function find_user_by_account($acct)
{
    $st = db()->prepare('SELECT * FROM users WHERE account_number = ?');
    $st->execute(array($acct));
    return $st->fetch();
}

/**
 * Create a transfer. Returns array(ok=>bool, msg=>string, ref=>string).
 * Funds are held immediately (debit posted as pending or completed).
 */
function create_transfer($user, $data)
{
    $amount = round((float)$data['amount'], 2);
    $fee    = transfer_fee($amount);
    $total  = $amount + $fee;
    $kind   = $data['kind']; // internal | domestic | wire

    if ($amount < (float)setting('min_transfer', '1')) {
        return array('ok' => false, 'msg' => 'Minimum transfer is ' . money(setting('min_transfer', '1')) . '.');
    }
    if ($amount > (float)setting('max_transfer', '1000000')) {
        return array('ok' => false, 'msg' => 'Maximum per transfer is ' . money(setting('max_transfer')) . '.');
    }
    $available = available_balance($user['id']);
    if ($total > $available) {
        $held = held_amount($user['id']);
        return array('ok' => false, 'msg' => 'Insufficient funds. You need ' . money($total)
            . ' including fees and only ' . money($available) . ' is available'
            . ($held > 0 ? ' (' . money($held) . ' is held against payments still clearing).' : '.'));
    }
    if (spent_today($user['id']) + $total > (float)setting('daily_limit', '1000000')) {
        return array('ok' => false, 'msg' => 'This would exceed your daily limit of ' . money(setting('daily_limit')) . '.');
    }
    if ((int)$user['transfer_locked'] === 1) {
        return array('ok' => false, 'msg' => $user['lock_reason'] ? $user['lock_reason'] : 'Transfers are on hold for this account.');
    }

    $needsApproval = ($kind === 'internal')
        ? setting('approve_internal', '0') === '1'
        : setting('approve_transfers', '1') === '1';

    $ref = make_ref('TRF');
    $status = $needsApproval ? 'pending' : 'completed';

    db()->prepare('INSERT INTO transfers
        (user_id, ref, kind, amount, fee, total, beneficiary_name, beneficiary_account, bank_name, bank_address, swift, routing, iban, country, narration, status, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(array(
            $user['id'], $ref, $kind, $amount, $fee, $total,
            $data['beneficiary_name'], $data['beneficiary_account'], $data['bank_name'],
            isset($data['bank_address']) ? $data['bank_address'] : '',
            isset($data['swift']) ? $data['swift'] : '',
            isset($data['routing']) ? $data['routing'] : '',
            isset($data['iban']) ? $data['iban'] : '',
            isset($data['country']) ? $data['country'] : '',
            $data['narration'], $status, now()
        ));

    $label = $kind === 'internal' ? 'Transfer to ' : 'Outward transfer to ';
    post_transaction($user['id'], 'debit', 'transfer_out', $amount,
        $label . $data['beneficiary_name'] . ($data['narration'] ? ' — ' . $data['narration'] : ''),
        array('fee' => $fee, 'status' => $status, 'ref' => $ref,
              'channel' => $kind, 'counterparty' => $data['beneficiary_name'] . ' · ' . $data['beneficiary_account']));

    if ($status === 'completed') {
        credit_beneficiary_if_internal($ref);
    }

    return array('ok' => true, 'ref' => $ref, 'status' => $status,
        'msg' => $status === 'pending'
            ? 'Transfer submitted. It is queued for approval.'
            : 'Transfer completed.');
}

/** If the transfer is internal, credit the receiving customer. */
function credit_beneficiary_if_internal($ref)
{
    $st = db()->prepare('SELECT * FROM transfers WHERE ref = ?');
    $st->execute(array($ref));
    $t = $st->fetch();
    if (!$t || $t['kind'] !== 'internal') return;

    $rcv = find_user_by_account($t['beneficiary_account']);
    if (!$rcv) return;
    $sender = find_user($t['user_id']);
    post_transaction($rcv['id'], 'credit', 'transfer_in', $t['amount'],
        'Transfer from ' . user_name($sender) . ($t['narration'] ? ' — ' . $t['narration'] : ''),
        array('status' => 'completed', 'ref' => $t['ref'], 'channel' => 'internal',
              'counterparty' => user_name($sender) . ' · ' . $sender['account_number']));
}

function approve_transfer($id, $decision, $note = '')
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM transfers WHERE id = ?');
    $st->execute(array($id));
    $t = $st->fetch();
    if (!$t || $t['status'] !== 'pending') return false;

    $admin = auth_user();
    $tx = tx_by_ref($t['ref']);

    if ($decision === 'approve') {
        ensure_completed($t['ref'], $t['user_id'], 'debit', 'transfer_out', $t['amount'],
            'Outward transfer to ' . $t['beneficiary_name'],
            array('fee' => $t['fee'], 'channel' => $t['kind'],
                  'counterparty' => $t['beneficiary_name'] . ' · ' . $t['beneficiary_account']));
        $pdo->prepare('UPDATE transfers SET status=?, admin_note=?, approved_by=?, approved_at=? WHERE id=?')
            ->execute(array('completed', $note, $admin['id'], now(), $id));
        credit_beneficiary_if_internal($t['ref']);
    } else {
        if ($tx) settle_transaction($tx['id'], 'declined');
        $pdo->prepare('UPDATE transfers SET status=?, admin_note=?, approved_by=?, approved_at=? WHERE id=?')
            ->execute(array('declined', $note, $admin['id'], now(), $id));
    }
    audit('transfer_' . $decision, 'transfer', $id, $t['ref'] . ' ' . money($t['amount']));
    return $t;
}

/* ---------- deposits ---------- */

function approve_deposit($id, $decision, $note = '')
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM deposits WHERE id = ?');
    $st->execute(array($id));
    $d = $st->fetch();
    if (!$d || $d['status'] !== 'pending') return false;

    $admin = auth_user();
    $tx = tx_by_ref($d['ref']);

    if ($decision === 'approve') {
        ensure_completed($d['ref'], $d['user_id'], 'credit', 'deposit', $d['amount'],
            'Deposit — ' . $d['method'], array('channel' => strtolower($d['method'])));
        $pdo->prepare('UPDATE deposits SET status=?, admin_note=?, approved_by=?, approved_at=? WHERE id=?')
            ->execute(array('approved', $note, $admin['id'], now(), $id));
    } else {
        if ($tx) settle_transaction($tx['id'], 'declined');
        $pdo->prepare('UPDATE deposits SET status=?, admin_note=?, approved_by=?, approved_at=? WHERE id=?')
            ->execute(array('declined', $note, $admin['id'], now(), $id));
    }
    audit('deposit_' . $decision, 'deposit', $id, $d['ref'] . ' ' . money($d['amount']));
    return $d;
}

/* ---------- withdrawals ---------- */

function approve_withdrawal($id, $decision, $note = '')
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ?');
    $st->execute(array($id));
    $w = $st->fetch();
    if (!$w || $w['status'] !== 'pending') return false;

    $admin = auth_user();
    $tx = tx_by_ref($w['ref']);

    if ($decision === 'approve') {
        ensure_completed($w['ref'], $w['user_id'], 'debit', 'withdrawal', $w['amount'],
            'Withdrawal to ' . $w['destination'],
            array('fee' => $w['fee'], 'channel' => strtolower($w['method']),
                  'counterparty' => $w['destination']));
        $pdo->prepare('UPDATE withdrawals SET status=?, admin_note=?, approved_by=?, approved_at=? WHERE id=?')
            ->execute(array('approved', $note, $admin['id'], now(), $id));
    } else {
        if ($tx) settle_transaction($tx['id'], 'declined');
        $pdo->prepare('UPDATE withdrawals SET status=?, admin_note=?, approved_by=?, approved_at=? WHERE id=?')
            ->execute(array('declined', $note, $admin['id'], now(), $id));
    }
    audit('withdrawal_' . $decision, 'withdrawal', $id, $w['ref'] . ' ' . money($w['amount']));
    return $w;
}

/* ---------- loans ---------- */

/** Flat-rate annual interest, spread over the tenure. */
function loan_quote($amount, $months, $rate = null)
{
    $rate = $rate === null ? (float)setting('loan_rate', '4.5') : (float)$rate;
    $years = $months / 12;
    $interest = $amount * ($rate / 100) * $years;
    $total = round($amount + $interest, 2);
    return array(
        'rate' => $rate,
        'interest' => round($interest, 2),
        'total' => $total,
        'monthly' => round($total / max(1, $months), 2),
    );
}

function approve_loan($id, $decision, $note = '')
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM loans WHERE id = ?');
    $st->execute(array($id));
    $l = $st->fetch();
    if (!$l || $l['status'] !== 'pending') return false;

    $admin = auth_user();
    if ($decision === 'approve') {
        post_transaction($l['user_id'], 'credit', 'loan', $l['amount'],
            'Loan disbursement — ' . $l['ref'], array('channel' => 'loan', 'ref' => make_ref('LND')));
        $due = date('Y-m-d H:i:s', strtotime('+' . (int)$l['tenure_months'] . ' months'));
        $pdo->prepare('UPDATE loans SET status=?, admin_note=?, approved_by=?, disbursed_at=?, due_at=? WHERE id=?')
            ->execute(array('active', $note, $admin['id'], now(), $due, $id));
    } else {
        $pdo->prepare('UPDATE loans SET status=?, admin_note=?, approved_by=? WHERE id=?')
            ->execute(array('declined', $note, $admin['id'], $id));
    }
    audit('loan_' . $decision, 'loan', $id, $l['ref'] . ' ' . money($l['amount']));
    return $l;
}

function repay_loan($loanId, $userId, $amount)
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM loans WHERE id = ? AND user_id = ?');
    $st->execute(array($loanId, $userId));
    $l = $st->fetch();
    if (!$l || $l['status'] !== 'active') return array('ok' => false, 'msg' => 'That loan is not open for repayment.');

    $outstanding = round((float)$l['total_repayable'] - (float)$l['amount_repaid'], 2);
    $amount = round(min($amount, $outstanding), 2);
    if ($amount <= 0) return array('ok' => false, 'msg' => 'Enter an amount above zero.');
    if ($amount > available_balance($userId)) {
        return array('ok' => false, 'msg' => 'Insufficient available funds for this repayment.');
    }

    $ref = make_ref('RPY');
    post_transaction($userId, 'debit', 'repayment', $amount, 'Loan repayment — ' . $l['ref'],
        array('channel' => 'loan', 'ref' => $ref));
    $pdo->prepare('INSERT INTO loan_repayments (loan_id, user_id, amount, ref, created_at) VALUES (?,?,?,?,?)')
        ->execute(array($loanId, $userId, $amount, $ref, now()));

    $repaid = round((float)$l['amount_repaid'] + $amount, 2);
    $status = $repaid >= (float)$l['total_repayable'] - 0.01 ? 'repaid' : 'active';
    $pdo->prepare('UPDATE loans SET amount_repaid = ?, status = ? WHERE id = ?')
        ->execute(array($repaid, $status, $loanId));

    return array('ok' => true, 'msg' => 'Repayment of ' . money($amount) . ' posted.', 'status' => $status);
}

/* ---------- cards ---------- */

function generate_card_number($brand = 'Visa')
{
    $prefix = $brand === 'Mastercard' ? '5' . random_int(1, 5) : '4';
    $n = $prefix;
    while (strlen($n) < 15) $n .= random_int(0, 9);
    // Luhn check digit
    $sum = 0; $alt = true;
    for ($i = strlen($n) - 1; $i >= 0; $i--) {
        $d = (int)$n[$i];
        if ($alt) { $d *= 2; if ($d > 9) $d -= 9; }
        $sum += $d; $alt = !$alt;
    }
    $check = (10 - ($sum % 10)) % 10;
    return $n . $check;
}

function mask_card($number)
{
    return substr($number, 0, 4) . ' •••• •••• ' . substr($number, -4);
}

function group_card($number)
{
    return trim(chunk_split($number, 4, ' '));
}

/* ---------- stats ---------- */

function user_stats($userId)
{
    $pdo = db();
    $q = function ($sql, $args) use ($pdo) {
        $s = $pdo->prepare($sql); $s->execute($args); $r = $s->fetch();
        return $r ? (float)$r['v'] : 0;
    };
    return array(
        'in'      => $q("SELECT COALESCE(SUM(amount),0) v FROM transactions WHERE user_id=? AND direction='credit' AND status='completed'", array($userId)),
        'out'     => $q("SELECT COALESCE(SUM(amount+fee),0) v FROM transactions WHERE user_id=? AND direction='debit' AND status='completed'", array($userId)),
        'pending' => $q("SELECT COALESCE(SUM(amount+fee),0) v FROM transactions WHERE user_id=? AND status='pending'", array($userId)),
        'loans'   => $q("SELECT COALESCE(SUM(total_repayable - amount_repaid),0) v FROM loans WHERE user_id=? AND status='active'", array($userId)),
    );
}

function count_where($table, $where = '1=1', $args = array())
{
    $st = db()->prepare("SELECT COUNT(*) c FROM $table WHERE $where");
    $st->execute($args);
    $r = $st->fetch();
    return (int)$r['c'];
}

function sum_where($table, $col, $where = '1=1', $args = array())
{
    $st = db()->prepare("SELECT COALESCE(SUM($col),0) s FROM $table WHERE $where");
    $st->execute($args);
    $r = $st->fetch();
    return (float)$r['s'];
}

/** Total items waiting on an admin decision. */
function pending_counts()
{
    return array(
        'deposits'    => count_where('deposits', "status='pending'"),
        'withdrawals' => count_where('withdrawals', "status='pending'"),
        'transfers'   => count_where('transfers', "status='pending'"),
        'loans'       => count_where('loans', "status='pending'"),
        'cards'       => count_where('cards', "status='pending'"),
        'kyc'         => count_where('kyc_documents', "status='pending'"),
        'enquiries'   => count_safe('enquiries', "status='new'"),
    );
}
