<?php
/**
 * backend/lib/hold_release.php — CAZACOM
 *
 * One release path, shared by cron/expire_holds.php and (optionally)
 * api/.../release_hold.php.
 *
 * mobile_money_accounts has no held_balance column: hold.php moves funds
 * straight out of `balance`, so releasing moves them straight back in.
 * That is the exact reverse of the placement, and matches what
 * release_hold.php already does by hand.
 *
 * Idempotent: a hold that is RELEASED, COMMITTED, DEBITED or EXPIRED
 * returns released=false with ALREADY_RESOLVED, never an error and never
 * a second credit.
 */

declare(strict_types=1);

/**
 * Release one hold. Call INSIDE a transaction, after selecting the hold
 * row FOR UPDATE.
 *
 * @param string $reason EXPIRED | REQUESTED
 */
function release_hold(PDO $db, array $hold, string $reason, string $actor): array
{
    $ref    = $hold['hold_reference'];
    $status = strtoupper((string)$hold['status']);

    if ($status !== 'HELD') {
        return [
            'released'       => false,
            'reason'         => 'ALREADY_RESOLVED',
            'hold_reference' => $ref,
            'status'         => $status,
        ];
    }

    $amount = (float)$hold['amount'];
    $userId = (int)$hold['user_id'];

    // Lock the wallet and capture the balance before the credit.
    $stmt = $db->prepare("
        SELECT balance FROM mobile_money_accounts
        WHERE user_id = :user_id FOR UPDATE
    ");
    $stmt->execute([':user_id' => $userId]);
    $balanceBefore = $stmt->fetchColumn();

    if ($balanceBefore === false) {
        // The account vanished between hold and expiry. Do not mark the
        // hold released — that would lose the customer's money silently.
        throw new RuntimeException("Hold {$ref}: no mobile money account for user_id={$userId}");
    }
    $balanceBefore = (float)$balanceBefore;

    $stmt = $db->prepare("
        UPDATE mobile_money_accounts
        SET balance = balance + :amount, last_updated = NOW()
        WHERE user_id = :user_id
        RETURNING balance
    ");
    $stmt->execute([':amount' => $amount, ':user_id' => $userId]);
    $balanceAfter = (float)$stmt->fetchColumn();

    $newStatus = ($reason === 'EXPIRED') ? 'EXPIRED' : 'RELEASED';

    $stmt = $db->prepare("
        UPDATE financial_holds
        SET status = :status::varchar,
            released_at = NOW(),
            release_reason = :reason,
            released_by = :actor
        WHERE hold_reference = :ref
    ");
    $stmt->execute([
        ':status' => $newStatus,
        ':reason' => $reason,
        ':actor'  => $actor,
        ':ref'    => $ref,
    ]);

    $latency = !empty($hold['expires_at'])
        ? max(0, time() - strtotime((string)$hold['expires_at']))
        : null;

    $stmt = $db->prepare("
        INSERT INTO hold_release_log
            (hold_reference, hold_id, user_id, amount, balance_before, balance_after,
             reason, released_by, source_reference, expires_at, latency_seconds)
        VALUES
            (:ref, :hold_id, :user_id, :amount, :before, :after,
             :reason, :actor, :src_ref, :expires_at, :latency)
    ");
    $stmt->execute([
        ':ref'        => $ref,
        ':hold_id'    => $hold['id'] ?? null,
        ':user_id'    => $userId,
        ':amount'     => $amount,
        ':before'     => $balanceBefore,
        ':after'      => $balanceAfter,
        ':reason'     => $reason,
        ':actor'      => $actor,
        ':src_ref'    => $hold['source_reference'] ?? null,
        ':expires_at' => $hold['expires_at'] ?? null,
        ':latency'    => $latency,
    ]);

    return [
        'released'        => true,
        'hold_reference'  => $ref,
        'status'          => $newStatus,
        'amount'          => $amount,
        'user_id'         => $userId,
        'balance_before'  => $balanceBefore,
        'balance_after'   => $balanceAfter,
        'latency_seconds' => $latency,
    ];
}

function log_cron_run(PDO $db, string $job, float $startedAt, int $processed, int $failed, string $detail = ''): void
{
    $stmt = $db->prepare("
        INSERT INTO cron_runs (job, started_at, processed, failed, detail)
        VALUES (:job, to_timestamp(:started), :processed, :failed, :detail)
    ");
    $stmt->execute([
        ':job'       => $job,
        ':started'   => $startedAt,
        ':processed' => $processed,
        ':failed'    => $failed,
        ':detail'    => $detail,
    ]);
}
