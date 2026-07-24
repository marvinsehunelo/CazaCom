<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

// Check for required session data. Redirect to login if user is not authenticated.
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: login.php");
    exit;
}

// Extract authenticated user data for use in the dashboard
$loggedInUserId = $_SESSION['user']['id'];
$loggedInUserName = $_SESSION['user']['name'];
$loggedInUserPhone = isset($_SESSION['user']['phone_number']) ? $_SESSION['user']['phone_number'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CazaCom Portal - Mobile Money Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php if (file_exists(__DIR__ . '/dist/output.css')): ?>
    <link href="/dist/output.css" rel="stylesheet">
<?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
<?php endif; ?>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
body { 
    font-family: 'Poppins', sans-serif; 
    background-color: #080808;
    color: #f5f5f5;
    min-height: 100vh; 
    padding: 20px;
}
.portal-container { 
    background-color: #111111;
    max-width: 1200px; 
    width: 100%; 
    border: 1px solid #1f1f1f;
    box-shadow: 0 0 50px rgba(255, 153, 0, 0.05);
    border-radius: 0; 
}

.btn-tab { 
    background: #1a1a1a; 
    color: #fff; 
    font-weight: 500; 
    transition: all 0.2s;
    border: 1px solid #333; 
    border-radius: 0;
    padding: 0.75rem 1.5rem;
}
.btn-tab.active { 
    background: #ff9900; 
    color: #000; 
    border-color: #ff9900;
    box-shadow: 0 0 10px rgba(255, 153, 0, 0.4), inset 0 0 0 1px #000;
}

.btn-action { 
    background: #ff9900; 
    color: #000; 
    font-weight: 600; 
    transition: 0.2s; 
    border: 1px solid #ff9900;
    border-radius: 0;
    padding: 0.75rem 1.5rem;
}
.btn-action:hover { 
    background: #e68a00; 
    box-shadow: 0 0 12px rgba(255, 153, 0, 0.8);
}
.btn-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-action-outline {
    background: transparent;
    color: #00ccff;
    font-weight: 600;
    transition: 0.2s;
    border: 1px solid #00ccff;
    border-radius: 0;
    padding: 0.75rem 1.5rem;
}
.btn-action-outline:hover {
    background: rgba(0, 204, 255, 0.1);
    box-shadow: 0 0 12px rgba(0, 204, 255, 0.5);
}
.btn-action-outline:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.input-field {
    background-color: #1a1a1a;
    border: 1px solid #333;
    color: #fff;
    border-radius: 0;
    transition: border-color 0.2s;
}
.input-field:focus {
    border-color: #ff9900;
    outline: none;
}

.balance-widget {
    background-color: #1a1a1a;
    border: 1px solid #1f1f1f;
    box-shadow: inset 0 0 5px rgba(255, 153, 0, 0.05);
    transition: all 0.3s ease;
}
.balance-widget:hover {
    border-color: #ff9900;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 153, 0, 0.1);
}

.wallet-mobile {
    border-left: 4px solid #ff9900;
}
.wallet-sms {
    border-left: 4px solid #00ccff;
}
.wallet-calls {
    border-left: 4px solid #00ff88;
}

.section-header {
    border-bottom: 2px solid #ff9900;
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.section-header.sms-header {
    border-bottom-color: #00ccff;
}
.section-header.calls-header {
    border-bottom-color: #00ff88;
}

.wallet-subsection {
    background-color: #0f0f0f;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #222;
}

.loading-spinner {
    border: 2px solid #333;
    border-top: 2px solid #ff9900;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    display: inline-block;
    margin-right: 8px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.error-message {
    color: #ff4444;
    background: rgba(255, 68, 68, 0.1);
    border: 1px solid #ff4444;
    padding: 1rem;
    margin: 1rem 0;
    text-align: center;
}

/* SMS Message Box - Click to expand */
.sms-message-box {
    animation: fadeIn 0.3s ease-in-out;
    cursor: pointer;
    transition: all 0.2s ease;
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sms-message-box:hover {
    color: #00ccff;
    opacity: 0.8;
}
.sms-message-box.expanded {
    max-width: 500px;
    white-space: normal;
    word-wrap: break-word;
    background: rgba(0, 204, 255, 0.05);
    padding: 4px 8px;
    border-radius: 4px;
}

/* Full message modal */
.message-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
}
.message-modal-overlay.active {
    display: flex;
}
.message-modal-content {
    background: #1a1a1a;
    border: 1px solid #00ccff;
    border-radius: 0;
    max-width: 600px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    padding: 30px;
    box-shadow: 0 0 60px rgba(0, 204, 255, 0.1);
}
.message-modal-content .close-btn {
    float: right;
    background: none;
    border: none;
    color: #fff;
    font-size: 28px;
    cursor: pointer;
    padding: 0 8px;
    line-height: 1;
}
.message-modal-content .close-btn:hover {
    color: #ff4444;
}
.message-modal-content .msg-from {
    color: #00ccff;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
}
.message-modal-content .msg-time {
    color: #666;
    font-size: 12px;
    margin-bottom: 16px;
}
.message-modal-content .msg-body {
    color: #f5f5f5;
    font-size: 15px;
    line-height: 1.8;
    white-space: pre-wrap;
    word-wrap: break-word;
    background: #0f0f0f;
    padding: 16px;
    border: 1px solid #2a2a2a;
    border-radius: 4px;
}
.message-modal-content .msg-body .sms-content {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    color: #e0e0e0;
}
.message-modal-content .msg-body .sms-content .amount {
    color: #ff9900;
    font-weight: 700;
}
.message-modal-content .msg-body .sms-content .voucher {
    color: #00ccff;
    font-weight: 600;
}
.message-modal-content .msg-body .sms-content .pin {
    color: #ff6b6b;
    font-weight: 700;
}
.message-modal-content .msg-body .sms-content .code {
    color: #ffd93d;
    font-weight: 700;
}
.message-modal-content .msg-body .sms-content .expiry {
    color: #ff6b6b;
}
.message-modal-content .msg-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body class="flex justify-center items-start pt-10 pb-10">
<div class="portal-container p-8 sm:p-12">
    <header class="mb-10 border-b border-[#ff9900]/20 pb-6">
        <h1 class="text-4xl font-extrabold uppercase text-center tracking-widest text-[#ff9900]">
            CazaCom Portal
        </h1>
        <div class="flex justify-between items-center mt-4 text-sm">
            <p class="text-gray-400">
                Session: <span class="text-white font-semibold tracking-wide"><?= htmlspecialchars($loggedInUserName) ?></span>
                <span class="text-xs text-gray-600">(ID: <?= htmlspecialchars($loggedInUserId) ?>)</span>
            </p>
            <a href="logout.php" class="text-xs text-red-500 hover:text-red-400 transition p-2 border border-red-500 rounded-none font-medium uppercase">
                <i data-lucide="log-out" class="inline w-3 h-3 mr-1"></i>Logout
            </a>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="flex justify-center gap-1 mb-10 flex-wrap">
        <button id="nav-mobile-money" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase active" onclick="openSection('mobile-money')">💰 Mobile Money</button>
        <button id="nav-sms" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('sms')">💬 SMS</button>
        <button id="nav-calls" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('calls')">📞 Calls</button>
    </nav>

    <!-- ==================== MOBILE MONEY SECTION ==================== -->
    <div id="mobile-money" class="section">
        <div class="section-header">
            <i data-lucide="smartphone" class="w-6 h-6 text-[#ff9900]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">Mobile Money</h2>
            <span class="ml-auto text-xs bg-[#ff9900]/20 text-[#ff9900] px-3 py-1">mobile_money_accounts</span>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
            <div class="p-4 balance-widget wallet-mobile flex flex-col items-center justify-center">
                <span class="text-xs uppercase text-gray-400 font-light tracking-widest">MM Balance</span>
                <span id="mobileMoneyBalance" class="text-3xl font-extrabold mt-1 text-[#ff9900]"><span class="loading-spinner"></span></span>
                <span class="text-xs text-gray-500 mt-1">mobile_money_accounts.balance</span>
            </div>
            <div class="p-4 balance-widget wallet-mobile flex flex-col items-center justify-center">
                <span class="text-xs uppercase text-gray-400 font-light tracking-widest">MM Credit</span>
                <span id="mobileMoneyCreditBalance" class="text-2xl font-bold mt-1 text-white"><span class="loading-spinner"></span></span>
                <span class="text-xs text-gray-500 mt-1">mobile_money_accounts.credit_balance</span>
            </div>
        </div>

        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i data-lucide="list" class="w-5 h-5 mr-2 text-[#ff9900]"></i>
                Mobile Money Transactions
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#ff9900]">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">Type</th>
                            <th class="p-2 text-left">Amount</th>
                            <th class="p-2 text-left">Fee</th>
                            <th class="p-2 text-left">Recipient</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="mobileMoneyTransactionsList">
                        <tr><td colspan="6" class="text-center p-4 text-gray-500"><span class="loading-spinner"></span>Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i data-lucide="arrow-left-right" class="w-5 h-5 mr-2 text-[#ff9900]"></i>
                Mobile Money Transfers
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#ff9900]">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">Recipient</th>
                            <th class="p-2 text-left">Amount</th>
                            <th class="p-2 text-left">Fee</th>
                            <th class="p-2 text-left">Network</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="mobileMoneyTransfersList">
                        <tr><td colspan="6" class="text-center p-4 text-gray-500">No transfers found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i data-lucide="phone" class="w-5 h-5 mr-2 text-[#ff9900]"></i>
                Airtime Purchases
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#ff9900]">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">Phone</th>
                            <th class="p-2 text-left">Amount</th>
                            <th class="p-2 text-left">Network</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="airtimePurchasesList">
                        <tr><td colspan="5" class="text-center p-4 text-gray-500">No purchases found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== SMS SECTION ==================== -->
    <div id="sms" class="section hidden">
        <div class="section-header sms-header">
            <i data-lucide="message-square" class="w-6 h-6 text-[#00ccff]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">SMS Services</h2>
            <span class="ml-auto text-xs bg-[#00ccff]/20 text-[#00ccff] px-3 py-1">sms + instant_sms</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="p-4 balance-widget" style="border-left: 4px solid #00ccff;">
                <span class="text-xs text-gray-400">Total SMS Cost</span>
                <span id="totalSmsCost" class="text-xl font-bold block"><span class="loading-spinner"></span></span>
            </div>
            <div class="p-4 balance-widget" style="border-left: 4px solid #00ccff;">
                <span class="text-xs text-gray-400">Inbox Messages</span>
                <span id="inboxCount" class="text-xl font-bold block"><span class="loading-spinner"></span></span>
            </div>
            <div class="p-4 balance-widget" style="border-left: 4px solid #00ccff;">
                <span class="text-xs text-gray-400">Outbox Messages</span>
                <span id="outboxCount" class="text-xl font-bold block"><span class="loading-spinner"></span></span>
            </div>
        </div>

        <!-- ============ COMPOSE / SEND SMS ============ -->
        <div class="wallet-subsection" style="border-left: 4px solid #00ccff;">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i data-lucide="send" class="w-5 h-5 mr-2 text-[#00ccff]"></i>
                Send SMS
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-start">
                <input type="tel" id="composeRecipient" placeholder="Recipient number (e.g. 26771234567)"
                       class="input-field p-3 text-sm sm:col-span-1" autocomplete="off">
                <textarea id="composeMessage" placeholder="Type your message..." maxlength="480" rows="2"
                          class="input-field p-3 text-sm sm:col-span-2" oninput="updateComposeCount()"></textarea>
                <div class="flex flex-col gap-2 sm:col-span-1">
                    <button id="composeSendBtn" class="btn-action py-3 text-sm w-full" onclick="sendInstantSms()">
                        <i data-lucide="send" class="inline w-4 h-4 mr-1"></i>Send
                    </button>
                    <span id="composeCount" class="text-xs text-gray-500 text-right">0 / 480</span>
                </div>
            </div>
            <p id="composeError" class="text-xs text-red-500 mt-2 hidden"></p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="wallet-subsection">
                <h3 class="text-lg font-semibold mb-4">SMS Records</h3>
                <div class="overflow-x-auto max-h-60 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#1a1a1a] text-[#00ccff] sticky top-0">
                            <tr>
                                <th class="p-2 text-left">Date</th>
                                <th class="p-2 text-left">To</th>
                                <th class="p-2 text-left">Cost</th>
                                <th class="p-2 text-left">Direction</th>
                            </tr>
                        </thead>
                        <tbody id="smsRecords">
                            <tr><td colspan="4" class="text-center p-4 text-gray-500"><span class="loading-spinner"></span>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="wallet-subsection">
                <h3 class="text-lg font-semibold mb-4">Instant SMS Inbox</h3>
                <p class="text-xs text-gray-500 mb-2">Click any message to read the full text and reply.</p>
                <div class="overflow-x-auto max-h-60 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#1a1a1a] text-[#00ccff] sticky top-0">
                            <tr>
                                <th class="p-2 text-left">From</th>
                                <th class="p-2 text-left">Message</th>
                                <th class="p-2 text-left">Received</th>
                                <th class="p-2 text-left">Provider</th>
                            </tr>
                        </thead>
                        <tbody id="instantSmsInbox">
                            <tr><td colspan="4" class="text-center p-4 text-gray-500"><span class="loading-spinner"></span>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="wallet-subsection lg:col-span-2">
                <h3 class="text-lg font-semibold mb-4">Instant SMS Outbox</h3>
                <p class="text-xs text-gray-500 mb-2">Click any message to read the full text.</p>
                <div class="overflow-x-auto max-h-60 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#1a1a1a] text-[#00ccff] sticky top-0">
                            <tr>
                                <th class="p-2 text-left">To</th>
                                <th class="p-2 text-left">Message</th>
                                <th class="p-2 text-left">Status</th>
                                <th class="p-2 text-left">Attempts</th>
                                <th class="p-2 text-left">Created</th>
                            </tr>
                        </thead>
                        <tbody id="instantSmsOutbox">
                            <tr><td colspan="5" class="text-center p-4 text-gray-500"><span class="loading-spinner"></span>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== CALLS SECTION ==================== -->
    <div id="calls" class="section hidden">
        <div class="section-header calls-header">
            <i data-lucide="phone" class="w-6 h-6 text-[#00ff88]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">Call Management</h2>
            <span class="ml-auto text-xs bg-[#00ff88]/20 text-[#00ff88] px-3 py-1">calls table</span>
        </div>
        
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Recent Calls</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ff88]">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">User ID</th>
                            <th class="p-2 text-left">Target Number</th>
                            <th class="p-2 text-left">Duration</th>
                            <th class="p-2 text-left">Cost</th>
                        </tr>
                    </thead>
                    <tbody id="callsList">
                        <tr><td colspan="5" class="text-center p-4 text-gray-500">No calls found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ NOTIFICATION MODAL ============ -->
    <div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-[#1a1a1a] p-6 w-96 rounded-none border border-[#ff9900] shadow-2xl">
            <h3 id="notificationTitle" class="text-xl font-bold text-[#ff9900] mb-3 border-b border-gray-700 pb-2">Notification</h3>
            <p id="notificationMessage" class="text-gray-300 mb-4 text-sm"></p>
            <button class="btn-action w-full py-2 text-sm" onclick="closeNotificationModal()">Close</button>
        </div>
    </div>

    <!-- ============ MESSAGE VIEWER MODAL ============ -->
    <div id="messageModal" class="message-modal-overlay" onclick="if(event.target===this)closeMessageModal()">
        <div class="message-modal-content">
            <button class="close-btn" onclick="closeMessageModal()">&times;</button>
            <div id="messageModalBody">
                <div class="msg-from" id="msgFrom">From: +26770000000</div>
                <div class="msg-time" id="msgTime">Received: 2024-01-01 12:00:00</div>
                <div class="msg-body" id="msgBody">Message content here</div>
                <div class="msg-actions" id="msgActions"></div>
            </div>
        </div>
    </div>
</div>

<script>
const loggedInUser = <?= json_encode($loggedInUserId) ?>;
const loggedInUserPhone = <?= json_encode($loggedInUserPhone) ?>;
const isRailway = window.location.hostname.includes('railway.app') || window.location.hostname.includes('up.railway.app');
const basePath = '/api.php';    
const baseApiUrl = window.location.origin + basePath;

// In-memory store for the currently loaded inbox/outbox messages, so the
// message modal can look raw data up by index instead of round-tripping
// through an HTML-escaped, string-interpolated onclick attribute (which is
// what broke on any message containing a quote, apostrophe, or line break).
let _smsInboxData = [];
let _smsOutboxData = [];

async function apiCall(endpoint, params = {}, method = 'GET') {
    const url = new URL(baseApiUrl);
    url.searchParams.append('path', endpoint);
    
    if (method === 'GET') {
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
    }
    
    const config = {
        method: method,
        headers: { 'Accept': 'application/json' },
        credentials: 'include'
    };
    
    if (method !== 'GET') {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(params);
    }

    try {
        const response = await fetch(url, config);
        const text = await response.text();
        if (!text) return { status: 'error', message: 'Empty response' };
        try { return JSON.parse(text); } catch (e) {
            console.error('JSON parse error:', text.substring(0, 200));
            return { status: 'error', message: 'Invalid JSON response' };
        }
    } catch (e) {
        console.error("API Call Error:", e);
        return { status: 'error', message: 'Network error: ' + e.message };
    }
}

function showNotification(title, message, isError = false) {
    const modal = document.getElementById('notificationModal');
    document.getElementById('notificationTitle').innerText = title;
    document.getElementById('notificationMessage').innerText = message;
    const titleEl = document.getElementById('notificationTitle');
    const borderEl = modal.querySelector('div');
    if (isError) {
        titleEl.className = 'text-xl font-bold text-red-500 mb-3 border-b border-gray-700 pb-2';
        borderEl.style.borderColor = '#ef4444';
    } else {
        titleEl.className = 'text-xl font-bold text-[#ff9900] mb-3 border-b border-gray-700 pb-2';
        borderEl.style.borderColor = '#ff9900';
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeNotificationModal() {
    document.getElementById('notificationModal').classList.remove('flex');
    document.getElementById('notificationModal').classList.add('hidden');
}

// ============ MESSAGE VIEWER ============
// Called with the RAW (unescaped) from/time/message strings and an optional
// replyTo number. Escaping for display happens once, here, in the right
// context (building innerHTML), instead of earlier when building an
// onclick attribute — which is what was corrupting messages before.
function openMessageModal(from, time, message, replyTo) {
    document.getElementById('msgFrom').textContent = 'From: ' + from;
    document.getElementById('msgTime').textContent = 'Received: ' + time;

    let formattedMsg = escapeHtml(message || '');
    // Highlight amounts
    formattedMsg = formattedMsg.replace(/(\d+\.?\d*)\s*(BWP|Pula|USD|EUR|GBP)/gi, '<span class="amount">$1 $2</span>');
    // Highlight voucher numbers (12 digits)
    formattedMsg = formattedMsg.replace(/\b(\d{12})\b/g, '<span class="voucher">$1</span>');
    // Highlight PINs (6 digits)
    formattedMsg = formattedMsg.replace(/\b(\d{6})\b/g, '<span class="pin">$1</span>');
    // Highlight auth codes (8 digits)
    formattedMsg = formattedMsg.replace(/\b(\d{8})\b/g, '<span class="code">$1</span>');
    // Highlight expiry dates
    formattedMsg = formattedMsg.replace(/(\d{2}\s+[A-Za-z]+\s+\d{4}\s+\d{2}:\d{2})/g, '<span class="expiry">$1</span>');

    document.getElementById('msgBody').innerHTML = '<div class="sms-content">' + formattedMsg + '</div>';

    const actionsEl = document.getElementById('msgActions');
    actionsEl.innerHTML = '';
    if (replyTo) {
        const replyBtn = document.createElement('button');
        replyBtn.className = 'btn-action-outline text-sm';
        replyBtn.innerHTML = '<i data-lucide="reply" class="inline w-4 h-4 mr-1"></i>Reply';
        replyBtn.addEventListener('click', () => replyToMessage(replyTo));
        actionsEl.appendChild(replyBtn);
        if (window.lucide) lucide.createIcons();
    }

    document.getElementById('messageModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Opens a specific inbox message by its index in _smsInboxData.
function openInboxMessage(index) {
    const msg = _smsInboxData[index];
    if (!msg) return;
    const from = msg.sender_number || 'N/A';
    const time = msg.created_at ? new Date(msg.created_at).toLocaleString() : 'N/A';
    openMessageModal(from, time, msg.message || '', from !== 'N/A' ? from : null);
}

// Opens a specific outbox message by its index in _smsOutboxData.
function openOutboxMessage(index) {
    const msg = _smsOutboxData[index];
    if (!msg) return;
    const to = msg.target_number || msg.recipient || 'N/A';
    const time = msg.created_at ? new Date(msg.created_at).toLocaleString() : 'N/A';
    openMessageModal('To: ' + to, time, msg.message || '', null);
}

// Pre-fills the compose box with a recipient, closes the modal, and
// scrolls/focuses the message field so the user can just start typing.
function replyToMessage(recipient) {
    closeMessageModal();
    const recipientEl = document.getElementById('composeRecipient');
    const messageEl = document.getElementById('composeMessage');
    recipientEl.value = recipient;
    messageEl.value = '';
    updateComposeCount();
    recipientEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    messageEl.focus();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function openSection(id) {
    document.querySelectorAll('.section').forEach(sec => sec.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    document.querySelectorAll('.btn-tab').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`nav-${id}`).classList.add('active');

    switch(id) {
        case 'mobile-money': loadMobileMoneyData(); break;
        case 'sms': loadSmsData(); break;
        case 'calls': loadCallsData(); break;
    }
}

// ============ MOBILE MONEY ============
async function loadMobileMoneyData() {
    try {
        const balanceRes = await apiCall('mm/balance', {}, 'GET');
        if (balanceRes.status === 'success') {
            document.getElementById('mobileMoneyBalance').innerHTML = `BWP${parseFloat(balanceRes.mobile_money_balance || 0).toFixed(2)}`;
            document.getElementById('mobileMoneyCreditBalance').innerHTML = `BWP${parseFloat(balanceRes.credit_balance || 0).toFixed(2)}`;
        }
        const historyRes = await apiCall('mm/history', {}, 'GET');
        displayMobileMoneyTransactions(historyRes.transactions || []);
    } catch (error) {
        document.getElementById('mobileMoneyBalance').innerHTML = 'BWP0.00';
        document.getElementById('mobileMoneyCreditBalance').innerHTML = 'BWP0.00';
    }
}

function displayMobileMoneyTransactions(transactions) {
    const tbody = document.getElementById('mobileMoneyTransactionsList');
    if (!transactions || !transactions.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-gray-500">No transactions found</td></tr>';
        return;
    }
    tbody.innerHTML = transactions.map(tx => `
        <tr class="border-b border-gray-800">
            <td class="p-2">${new Date(tx.created_at).toLocaleString()}</td>
            <td class="p-2">${escapeHtml(tx.type || '')}</td>
            <td class="p-2 ${tx.type === 'deposit' ? 'text-[#ff9900]' : 'text-red-400'}">BWP ${parseFloat(tx.amount).toFixed(2)}</td>
            <td class="p-2">BWP ${parseFloat(tx.fee || 0).toFixed(2)}</td>
            <td class="p-2">${escapeHtml(tx.recipient_phone || '-')}</td>
            <td class="p-2"><span class="px-2 py-1 text-xs ${tx.status === 'completed' ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-400'}">${escapeHtml(tx.status || '')}</span></td>
        </tr>
    `).join('');
}

// ============ SMS ============
async function loadSmsData() {
    try {
        const historyRes = await apiCall('sms/history', {}, 'GET');
        if (historyRes.status === 'success' && historyRes.history) {
            displaySmsRecords(historyRes.history);
            const totalCost = historyRes.history.reduce((sum, sms) => sum + parseFloat(sms.cost || 0), 0);
            document.getElementById('totalSmsCost').innerHTML = `BWP ${totalCost.toFixed(2)}`;
            const sentMessages = historyRes.history.filter(sms => sms.direction === 'sent');
            const receivedMessages = historyRes.history.filter(sms => sms.direction === 'received');
            document.getElementById('inboxCount').innerHTML = receivedMessages.length;
            document.getElementById('outboxCount').innerHTML = sentMessages.length;
            displaySmsInbox(receivedMessages);
            displaySmsOutbox(sentMessages);
        }
    } catch (error) {
        document.getElementById('smsRecords').innerHTML = '<tr><td colspan="4" class="text-center p-4 text-red-500">Error loading SMS</td></tr>';
    }
}

function displaySmsRecords(smsRecords) {
    const tbody = document.getElementById('smsRecords');
    if (!smsRecords || !smsRecords.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No SMS records</td></tr>';
        return;
    }
    tbody.innerHTML = smsRecords.map(sms => `
        <tr class="border-b border-gray-800">
            <td class="p-2">${new Date(sms.created_at).toLocaleString()}</td>
            <td class="p-2">${escapeHtml(sms.target_number || sms.recipient || 'N/A')}</td>
            <td class="p-2">BWP ${parseFloat(sms.cost || 0).toFixed(2)}</td>
            <td class="p-2"><span class="px-2 py-1 text-xs ${sms.direction === 'sent' ? 'bg-blue-900 text-blue-400' : 'bg-green-900 text-green-400'}">${escapeHtml(sms.direction || '')}</span></td>
        </tr>
    `).join('');
}

// Rows now carry a plain integer index (data-msg-index) instead of the
// message text itself — no string-escaping-into-JS-string edge cases left.
// A single delegated listener (wired once in DOMContentLoaded) reads the
// index and looks the real object up in _smsInboxData / _smsOutboxData.
function displaySmsInbox(receivedMessages) {
    _smsInboxData = receivedMessages || [];
    const tbody = document.getElementById('instantSmsInbox');
    if (!_smsInboxData.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No received messages</td></tr>';
        return;
    }
    tbody.innerHTML = _smsInboxData.map((msg, i) => {
        const messageText = msg.message || '';
        const truncated = messageText.length > 50 ? messageText.substring(0, 50) + '...' : messageText;
        return `
        <tr class="border-b border-gray-800">
            <td class="p-2">${escapeHtml(msg.sender_number || 'N/A')}</td>
            <td class="p-2">
                <span class="sms-message-box" data-msg-index="${i}" data-msg-source="inbox" title="Click to read full message">
                    ${escapeHtml(truncated)}
                </span>
            </td>
            <td class="p-2">${msg.created_at ? new Date(msg.created_at).toLocaleString() : 'N/A'}</td>
            <td class="p-2">${msg.cost ? 'BWP ' + parseFloat(msg.cost).toFixed(2) : '-'}</td>
        </tr>
    `}).join('');
}

function displaySmsOutbox(sentMessages) {
    _smsOutboxData = sentMessages || [];
    const tbody = document.getElementById('instantSmsOutbox');
    if (!_smsOutboxData.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4 text-gray-500">No sent messages</td></tr>';
        return;
    }
    tbody.innerHTML = _smsOutboxData.map((msg, i) => {
        const messageText = msg.message || '';
        const truncated = messageText.length > 50 ? messageText.substring(0, 50) + '...' : messageText;
        return `
        <tr class="border-b border-gray-800">
            <td class="p-2">${escapeHtml(msg.target_number || msg.recipient || 'N/A')}</td>
            <td class="p-2">
                <span class="sms-message-box" data-msg-index="${i}" data-msg-source="outbox" title="Click to read full message">
                    ${escapeHtml(truncated)}
                </span>
            </td>
            <td class="p-2"><span class="px-2 py-1 text-xs ${msg.status === 'delivered' ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-400'}">${escapeHtml(msg.status || 'sent')}</span></td>
            <td class="p-2">${msg.attempts || 1}</td>
            <td class="p-2">${msg.created_at ? new Date(msg.created_at).toLocaleString() : 'N/A'}</td>
        </tr>
    `}).join('');
}

// Single delegated click handler for both inbox and outbox message cells.
// Delegation means newly rendered rows work automatically — no re-binding
// needed after every refresh.
document.addEventListener('click', function (e) {
    const target = e.target.closest('.sms-message-box');
    if (!target) return;
    const index = parseInt(target.getAttribute('data-msg-index'), 10);
    const source = target.getAttribute('data-msg-source');
    if (Number.isNaN(index)) return;
    if (source === 'inbox') openInboxMessage(index);
    else if (source === 'outbox') openOutboxMessage(index);
});

// ============ SEND SMS ============
function updateComposeCount() {
    const messageEl = document.getElementById('composeMessage');
    const countEl = document.getElementById('composeCount');
    const len = messageEl.value.length;
    countEl.textContent = `${len} / 480`;
}

async function sendInstantSms() {
    const recipientEl = document.getElementById('composeRecipient');
    const messageEl = document.getElementById('composeMessage');
    const sendBtn = document.getElementById('composeSendBtn');
    const errorEl = document.getElementById('composeError');

    const recipient = recipientEl.value.trim();
    const message = messageEl.value.trim();

    errorEl.classList.add('hidden');
    errorEl.textContent = '';

    if (!recipient) {
        errorEl.textContent = 'Enter a recipient number.';
        errorEl.classList.remove('hidden');
        recipientEl.focus();
        return;
    }
    if (!message) {
        errorEl.textContent = 'Type a message before sending.';
        errorEl.classList.remove('hidden');
        messageEl.focus();
        return;
    }

    sendBtn.disabled = true;
    const originalLabel = sendBtn.innerHTML;
    sendBtn.innerHTML = '<span class="loading-spinner" style="border-top-color:#000;"></span>Sending...';

    try {
        // NOTE: endpoint/param names assumed as sms/send with {recipient, message}.
        // Adjust to match whatever api.php actually expects if it differs.
        const res = await apiCall('sms/send', { recipient: recipient, message: message }, 'POST');
        if (res.status === 'success') {
            recipientEl.value = '';
            messageEl.value = '';
            updateComposeCount();
            showNotification('Message Sent', 'Your SMS was sent successfully.');
            loadSmsData();
        } else {
            errorEl.textContent = res.message || 'Failed to send message.';
            errorEl.classList.remove('hidden');
        }
    } catch (err) {
        errorEl.textContent = 'Network error while sending. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalLabel;
        if (window.lucide) lucide.createIcons();
    }
}

// ============ CALLS ============
async function loadCallsData() {
    document.getElementById('callsList').innerHTML = '<tr><td colspan="5" class="text-center p-4 text-gray-500">Calls feature coming soon</td></tr>';
}

// Close message modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMessageModal();
        closeNotificationModal();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
    openSection('mobile-money');
});
</script>
</body>
</html>
