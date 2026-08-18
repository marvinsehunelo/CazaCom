<?php
// Cazacom: Place hold on wallet funds
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../security/ApiAuthenticator.php';
require_once __DIR__ . '/../../../../security/CazacomResponseSigner.php';
use Security\ApiAuthenticator;

// FIX: was `new PDO(getenv('DATABASE_URL'))` — not a valid PDO DSN.
// Single Database connection reused for both auth and the queries
// below, same as debit.php / credit.php.
$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
$auth = new ApiAuthenticator($db);
$participant = $auth->requireAuth();
// requireAuth() already sends a 401 and exit()s internally on failure —
// execution only reaches here with a valid, authenticated $participant.

// ============================================================
// NEW: Response signing.
//
// Previously CAZACOM never signed any response — confirmed by
// hold_transactions.signature_chain showing "signature": null for
// every CAZACOM hold. This was invisible for single-source swaps
// (nothing checked for it) but broke multi-source pools, where
// AggregateSigner::signAggregate() requires a valid signature from
// EVERY contributing institution and fails the whole pool with
// "Invalid signature from: CAZACOM" when one is missing.
//
// TODO (ops/deploy): CAZACOM_SIGNING_KEY_PATH and
// CAZACOM_SIGNING_CERT_PATH must point at securely-deployed files —
// NEVER paths inside the git repo, NEVER the private key committed
// anywhere. Wire these through whatever secrets mechanism this
// deployment already uses for other credentials (this file's own
// DB connection is a reasonable model to follow).
//
// TODO (cross-team): the certificate at CAZACOM_SIGNING_CERT_PATH
// must be registered in VouchMorph's SignatureVerifier trust store
// before this will actually verify successfully on their side. Until
// that happens, responses will carry a real signature but
// VouchMorph will reject it as untrusted rather than missing —
// progress, but not the finish line. Coordinate with whoever owns
// participants.yaml / the trust store on that side.
// ============================================================
$signer = new CazacomResponseSigner(
    getenv('CAZACOM_SIGNING_KEY_PATH') ?: '/etc/cazacom/secrets/cazacom_private_key.pem',
    getenv('CAZACOM_SIGNING_CERT_PATH') ?: '/etc/cazacom/config/cazacom_certificate.pem'
);

// ============================================================
// FIX: was `if (!in_array('initiate_payment', $client['scopes']))`.
// $client was never defined in this version of the file — this is
// leftover from an earlier edit that switched from
// `$client = $auth->authenticate(...)` (which returned a client/scopes
// array) to `$participant = $auth->requireAuth()` (which returns a
// plain participant-name string) without updating this line. Once
// requireAuth() itself was fixed to actually authenticate correctly
// (see ApiAuthenticator.php's header-parsing fix), this became the
// very next line to execute and would fatal with "Undefined variable
// $client" on every successful auth.
//
// requireAuth() in the current ApiAuthenticator does not expose scopes
// at all — there is no equivalent data available here to check against.
// Rather than fabricate a scope check against data that doesn't exist,
// this block is disabled with a clear TODO. If per-participant scope
// enforcement (e.g. restricting which participants may initiate holds)
// is required, KeyVault/ApiAuthenticator need to be extended to track
// and return scopes per participant first — that's a real design
// decision for whoever owns Cazacom's auth model, not something to
// silently invent here.
// ============================================================
// TODO: no scope enforcement currently possible — ApiAuthenticator::requireAuth()
// returns only a participant name, not a scopes list. Restore a real
// check here once scopes are modeled, or confirm scope enforcement is
// intentionally not required for this endpoint.

$input = json_decode(file_get_contents("php://input"), true);
$reference = $input['reference'] ?? null;

// ============================================================
// FIX: was `$input['asset_id'] ?? null` — GenericBankClient never
// sends a field called 'asset_id'. addSourceIdentifier() populates
// 'source_identifier', 'wallet_phone', 'phone', 'national_id',
// 'email', and 'account_number' — never 'asset_id'. Every real hold
// request therefore looked up a null phone number and failed with
// "User not found", even though verify_wallet.php (which correctly
// reads 'source_identifier'/'phone') found the exact same wallet
// moments earlier in the same swap trace. Now checks the field names
// GenericBankClient actually populates, preferring 'source_identifier'
// first, with 'asset_id' kept as a last-resort fallback for any
// caller that does send it directly.
// ============================================================
$assetId = $input['source_identifier']
    ?? $input['phone']
    ?? $input['wallet_phone']
    ?? $input['asset_id']
    ?? null;

$amount = (float)($input['amount'] ?? 0);
$expiry = $input['expiry'] ?? date('Y-m-d H:i:s', strtotime('+24 hours'));
$accessToken = $input['access_token'] ?? null;
if (!$reference || !$assetId || $amount <= 0) {
    echo json_encode(["success" => false, "status" => "error", "message" => "Missing required fields"]);
    exit;
}
$db->beginTransaction();
try {
    // Get user and wallet
    $stmt = $db->prepare("SELECT id, phone_number FROM users WHERE phone_number = :phone");
    $stmt->execute(['phone' => $assetId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception("User not found");
    }
    // Check current balance
    $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE");
    $stmt->execute(['user_id' => $user['id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$wallet) {
        throw new Exception("No mobile money account found for this user");
    }
    $availableBalance = (float)$wallet['balance'];
    if ($availableBalance < $amount) {
        throw new Exception("Insufficient balance");
    }
    // Create hold
    $holdReference = 'HOLD-' . $reference . '-' . time();
    $stmt = $db->prepare("
        INSERT INTO financial_holds
        (hold_reference, user_id, amount, status, expires_at, created_at, source_reference)
        VALUES (:ref, :user_id, :amount, 'HELD', :expires, NOW(), :src_ref)
    ");
    $stmt->execute([
        'ref' => $holdReference,
        'user_id' => $user['id'],
        'amount' => $amount,
        'expires' => $expiry,
        'src_ref' => $reference
    ]);
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts
        SET balance = balance - :amount,
            last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute(['amount' => $amount, 'user_id' => $user['id']]);
    $db->commit();

    $responseBody = [
        // FIX: was missing a top-level 'success' boolean — same gap
        // found and fixed in credit.php, and (per the same pattern
        // confirmed independently in absa_participant.php's and
        // mtn_momo_participant.php's placeHold() returns) the likely
        // cause of the "Hold failed: <message that was actually a
        // success message>" contradiction seen across every
        // wallet-as-source hold this session. Whatever wraps this
        // call reads $result['success'] as the pass/fail signal and
        // falls back to $result['message'] for the error text when
        // that key is absent — a genuinely successful hold with no
        // 'success' key therefore gets reported as failed, using its
        // own success message as the "reason."
        "success" => true,
        "status" => "success",
        "hold_placed" => true,
        "hold_reference" => $holdReference,
        "hold_expiry" => $expiry,
        "amount_held" => $amount,
        "available_balance" => $availableBalance - $amount,
        "message" => "Hold placed successfully",
    ];

    // NEW: sign the response so multi-source pool assembly on
    // VouchMorph's side (AggregateSigner/SignatureVerifier) can
    // validate CAZACOM's hold the same way it already validates
    // ZURUBANK's and SACCUSSALIS's.
    $signed = $signer->sign($responseBody);
    $responseBody['signature'] = $signed['signature'];
    $responseBody['certificate'] = $signed['certificate'];
    $responseBody['timestamp'] = $signed['timestamp'];
    $responseBody['verification_method'] = 'certificate';

    echo json_encode($responseBody);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => $e->getMessage(),
        "hold_placed" => false
    ]);
}
