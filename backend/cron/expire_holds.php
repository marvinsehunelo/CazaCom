<?php
/**
 * backend/cron/expire_holds.php — CAZACOM
 *
 * Releases every expired hold, restoring the amount to the customer's
 * mobile money balance.
 *
 * Nothing here calls VouchMorph or waits for it. A hold must come off the
 * customer's money whether or not the orchestrator is running — that is
 * what makes the non-custodial claim true rather than asserted.
 *
 * Expiry wins: release_hold.php and debit.php both select the hold with
 * `status = 'HELD' ... FOR UPDATE`. Once this job flips the row to
 * EXPIRED, a later debit finds nothing and is rejected. Whichever gets
 * the row lock first wins; never both.
 *
 * CazaCom issues no cashout codes, so there is no companion code-expiry
 * job. Codes are issued by the destination institution and expire there,
 * an hour before this hold.
 *
 * Run every minute:
 *   * * * * * /usr/bin/php /var/www/cazacom/backend/cron/expire_holds.php >> /var/log/cazacom/expire_holds.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/hold_release.php';

const JOB        = 'expire_holds';
const BATCH_SIZE = 200;
const LOCK_KEY   = 8583201;

$startedAt = microtime(true);
$released  = 0;
$skipped   = 0;
$failed    = 0;

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    error_log('[' . JOB . '] database connection failed');
    exit(1);
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// One instance at a time. A second copy exits quietly.
$lock = $db->prepare('SELECT pg_try_advisory_lock(:k)');
$lock->execute([':k' => LOCK_KEY]);

if (!$lock->fetchColumn()) {
    error_log('[' . JOB . '] another instance is running — exiting');
    exit(0);
}

try {
    while (true) {
        $db->beginTransaction();

        // SKIP LOCKED steps over rows a debit or a manual release is
        // mid-way through; they resolve themselves and never reach here.
        $stmt = $db->prepare("
            SELECT id, hold_reference, user_id, amount, status,
                   source_reference, expires_at
            FROM   financial_holds
            WHERE  status = 'HELD' AND expires_at < NOW()
            ORDER  BY expires_at
            LIMIT  :limit
            FOR UPDATE SKIP LOCKED
        ");
        $stmt->bindValue(':limit', BATCH_SIZE, PDO::PARAM_INT);
        $stmt->execute();
        $holds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$holds) {
            $db->commit();
            break;
        }

        foreach ($holds as $hold) {
            try {
                $r = release_hold($db, $hold, 'EXPIRED', 'cron:' . JOB);

                if ($r['released']) {
                    $released++;
                    error_log(sprintf(
                        '[%s] released %s user=%d amount=%.2f balance %.2f->%.2f latency=%ss',
                        JOB, $hold['hold_reference'], $r['user_id'], $r['amount'],
                        $r['balance_before'], $r['balance_after'], $r['latency_seconds'] ?? '?'
                    ));
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                // One bad hold must not abort the batch; the rest still commit.
                $failed++;
                error_log('[' . JOB . '] FAILED ' . $hold['hold_reference'] . ': ' . $e->getMessage());
            }
        }

        $db->commit();

        if (count($holds) < BATCH_SIZE) {
            break;
        }
    }

    // ---------------------------------------------------------------
    // Alerts — anything here means money is in the wrong place
    // ---------------------------------------------------------------
    $orphans   = (int)$db->query('SELECT count(*) FROM v_orphaned_holds')->fetchColumn();
    $overlong  = (int)$db->query('SELECT count(*) FROM v_overlong_holds')->fetchColumn();
    $dupes     = (int)$db->query('SELECT count(*) FROM v_duplicate_holds')->fetchColumn();

    if ($orphans > 0) {
        error_log('[' . JOB . '] ALERT ' . $orphans . ' hold(s) still withholding funds past expiry');
    }
    if ($overlong > 0) {
        error_log('[' . JOB . '] ALERT ' . $overlong . ' hold(s) accepted with a window longer than 24h');
    }
    if ($dupes > 0) {
        error_log('[' . JOB . '] ALERT ' . $dupes . ' swap(s) with more than one live hold — retry double-deduct');
    }

    log_cron_run($db, JOB, $startedAt, $released, $failed,
        sprintf('skipped=%d orphans=%d overlong=%d duplicates=%d',
            $skipped, $orphans, $overlong, $dupes));

    error_log(sprintf('[%s] done: released=%d skipped=%d failed=%d in %.2fs',
        JOB, $released, $skipped, $failed, microtime(true) - $startedAt));

    exit($failed > 0 || $orphans > 0 || $dupes > 0 ? 1 : 0);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[' . JOB . '] FATAL: ' . $e->getMessage());
    log_cron_run($db, JOB, $startedAt, $released, $failed + 1, 'fatal: ' . $e->getMessage());
    exit(1);

} finally {
    $db->prepare('SELECT pg_advisory_unlock(:k)')->execute([':k' => LOCK_KEY]);
}
