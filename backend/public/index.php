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
<title>CazaCom Portal - Executive Dashboard</title>
<!-- Poppins font for professional, modern look -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Use local Tailwind if available, otherwise CDN for development -->
<?php if (file_exists(__DIR__ . '/dist/output.css')): ?>
    <link href="/dist/output.css" rel="stylesheet">
<?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
<?php endif; ?>
<!-- Lucide Icons for premium iconography -->
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* * V2.0 Executive Aesthetic: 
 * Dark Monochromatic Base, Sharp Lines, Slim Profiles, High-Contrast Data.
 */
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
    box-shadow: 0 0 50px rgba(0, 255, 200, 0.05);
    border-radius: 0; 
}

/* Slimmer Tab Buttons */
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
    background: #00ffc8; 
    color: #000; 
    border-color: #00ffc8;
    box-shadow: 0 0 10px rgba(0, 255, 200, 0.4), inset 0 0 0 1px #000;
}

/* Slimmer Action Buttons */
.btn-action { 
    background: #00ffc8; 
    color: #000; 
    font-weight: 600; 
    transition: 0.2s; 
    border: 1px solid #00ffc8;
    border-radius: 0;
    padding: 0.75rem 1.5rem;
}
.btn-action:hover { 
    background: #00e6b8; 
    box-shadow: 0 0 12px rgba(0, 255, 200, 0.8);
}
.btn-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-secondary {
    background: #2a2a2a;
    color: #fff;
    font-weight: 500;
    transition: 0.2s;
    border: 1px solid #444;
    border-radius: 0;
    padding: 0.75rem 1.5rem;
}
.btn-secondary:hover {
    background: #333;
    border-color: #00ffc8;
}

.input-field {
    background-color: #1a1a1a;
    border: 1px solid #333;
    color: #fff;
    border-radius: 0;
    transition: border-color 0.2s;
}
.input-field:focus {
    border-color: #00ffc8;
    outline: none;
}

/* Premium Balance Card Widget Style */
.balance-widget {
    background-color: #1a1a1a;
    border: 1px solid #1f1f1f;
    box-shadow: inset 0 0 5px rgba(0, 255, 200, 0.05);
    transition: all 0.3s ease;
}
.balance-widget:hover {
    border-color: #00ffc8;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 255, 200, 0.1);
}

/* Wallet type specific styling */
.wallet-saccus {
    border-left: 4px solid #00ffc8;
}
.wallet-mobile {
    border-left: 4px solid #ff9900;
}
.wallet-main {
    border-left: 4px solid #ffffff;
}
.wallet-credit {
    border-left: 4px solid #ff3366;
}

.main-balance {
    border: 1px solid #00ffc8;
}
.main-balance:hover {
    box-shadow: 0 5px 20px rgba(0, 255, 200, 0.3);
}

.sms-message-box {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Section headers with accent */
.section-header {
    border-bottom: 2px solid #00ffc8;
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wallet-subsection {
    background-color: #0f0f0f;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #222;
}

/* Loading spinner */
.loading-spinner {
    border: 2px solid #333;
    border-top: 2px solid #00ffc8;
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
</style>
</head>
<body class="flex justify-center items-start pt-10 pb-10">
<div class="portal-container p-8 sm:p-12">
    <header class="mb-10 border-b border-[#00ffc8]/20 pb-6">
        <h1 class="text-4xl font-extrabold uppercase text-center tracking-widest text-[#00ffc8]">
            CazaCom Executive Portal
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
        <button id="nav-wallet" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase active" onclick="openSection('wallet')">Wallets</button>
        <button id="nav-mobile-money" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('mobile-money')">Mobile Money</button>
        <button id="nav-calls" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('calls')">Calls</button>
        <button id="nav-sms" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('sms')">SMS</button>
        <button id="nav-data" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('data')">Data</button>
        <button id="nav-transactions" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('transactions')">Transactions</button>
        <button id="nav-agents" class="btn-tab px-6 py-2 text-xs sm:text-sm uppercase" onclick="openSection('agents')">Agents</button>
    </nav>

    <!-- ==================== WALLETS SECTION ==================== -->
    <div id="wallet" class="section">
        <div class="section-header">
            <i data-lucide="wallet" class="w-6 h-6 text-[#00ffc8]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">Wallet Management</h2>
            <span class="ml-auto text-xs bg-[#00ffc8]/20 text-[#00ffc8] px-3 py-1">wallets table</span>
        </div>
        
        <!-- Wallet Balance Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 mb-8">
            <div class="p-4 balance-widget wallet-main flex flex-col items-center justify-center">
                <span class="text-xs uppercase text-gray-400 font-light tracking-widest">Main Balance</span>
                <span id="mainBalance" class="text-3xl font-extrabold mt-1 text-[#00ffc8]"><span class="loading-spinner"></span></span>
                <span class="text-xs text-gray-500 mt-1">wallets.balance</span>
            </div>
            <div class="p-4 balance-widget wallet-saccus flex flex-col items-center justify-center">
                <span class="text-xs uppercase text-gray-400 font-light tracking-widest">Saccus eWallet</span>
                <span id="saccusEwalletBalance" class="text-2xl font-bold mt-1 text-[#00ffc8]"><span class="loading-spinner"></span></span>
                <span class="text-xs text-gray-500 mt-1">wallets.saccus_ewallet_balance</span>
            </div>
            <div class="p-4 balance-widget wallet-credit flex flex-col items-center justify-center">
                <span class="text-xs uppercase text-gray-400 font-light tracking-widest">Credit Balance</span>
                <span id="creditBalance" class="text-2xl font-bold mt-1 text-[#ff3366]"><span class="loading-spinner"></span></span>
                <span class="text-xs text-gray-500 mt-1">wallets.credit_balance</span>
            </div>
            <div class="p-4 balance-widget flex flex-col items-center justify-center">
                <span class="text-xs uppercase text-gray-400 font-light tracking-widest">Agent Float</span>
                <span id="agentFloatTotal" class="text-2xl font-bold mt-1 text-white">BWP0.00</span>
                <span class="text-xs text-gray-500 mt-1">agent_float_accounts</span>
            </div>
        </div>

        <!-- Wallet Operations -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i data-lucide="arrow-left-right" class="w-5 h-5 mr-2 text-[#00ffc8]"></i>
                Wallet Operations
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <button class="btn-action text-sm" onclick="openTransferModal('wallet_to_saccus')">
                    <i data-lucide="arrow-right" class="w-4 h-4 mr-2 inline"></i>Balance → eWallet
                </button>
                <button class="btn-action text-sm" onclick="openTransferModal('saccus_to_wallet')">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2 inline"></i>eWallet → Balance
                </button>
                <button class="btn-action text-sm" onclick="openTransferModal('credit_to_balance')">
                    <i data-lucide="arrow-down" class="w-4 h-4 mr-2 inline"></i>Credit → Balance
                </button>
                <button class="btn-action text-sm" onclick="openTransferModal('balance_to_credit')">
                    <i data-lucide="arrow-up" class="w-4 h-4 mr-2 inline"></i>Balance → Credit
                </button>
            </div>
        </div>

        <!-- Recent Wallet Activity -->
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-4">Recent Wallet Transactions</h3>
            <div id="walletTransactions" class="space-y-2 max-h-60 overflow-y-auto">
                <p class="text-center text-gray-500 p-4"><span class="loading-spinner"></span>Loading transactions...</p>
            </div>
        </div>
    </div>

    <!-- ==================== MOBILE MONEY SECTION ==================== -->
    <div id="mobile-money" class="section hidden">
        <div class="section-header">
            <i data-lucide="smartphone" class="w-6 h-6 text-[#ff9900]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">Mobile Money</h2>
            <span class="ml-auto text-xs bg-[#ff9900]/20 text-[#ff9900] px-3 py-1">mobile_money_accounts</span>
        </div>
        
        <!-- Mobile Money Balance Cards -->
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

        <!-- Mobile Money Transactions Table -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Mobile Money Transactions</h3>
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

        <!-- Mobile Money Transfers -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Mobile Money Transfers</h3>
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

        <!-- Airtime Purchases -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Airtime Purchases</h3>
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

    <!-- ==================== CALLS SECTION ==================== -->
    <div id="calls" class="section hidden">
        <div class="section-header">
            <i data-lucide="phone" class="w-6 h-6 text-[#00ffc8]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">Call Management</h2>
            <span class="ml-auto text-xs bg-[#00ffc8]/20 text-[#00ffc8] px-3 py-1">calls table</span>
        </div>
        
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Recent Calls</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ffc8]">
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

    <!-- ==================== SMS SECTION ==================== -->
    <div id="sms" class="section hidden">
        <div class="section-header">
            <i data-lucide="message-square" class="w-6 h-6 text-[#00ffc8]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">SMS Services</h2>
            <span class="ml-auto text-xs bg-[#00ffc8]/20 text-[#00ffc8] px-3 py-1">sms + instant_sms</span>
        </div>

        <!-- SMS Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="p-4 balance-widget">
                <span class="text-xs text-gray-400">Total SMS Cost</span>
                <span id="totalSmsCost" class="text-xl font-bold block"><span class="loading-spinner"></span></span>
            </div>
            <div class="p-4 balance-widget">
                <span class="text-xs text-gray-400">Inbox Messages</span>
                <span id="inboxCount" class="text-xl font-bold block"><span class="loading-spinner"></span></span>
            </div>
            <div class="p-4 balance-widget">
                <span class="text-xs text-gray-400">Outbox Messages</span>
                <span id="outboxCount" class="text-xl font-bold block"><span class="loading-spinner"></span></span>
            </div>
        </div>

        <!-- SMS Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- SMS Records -->
            <div class="wallet-subsection">
                <h3 class="text-lg font-semibold mb-4">SMS Records</h3>
                <div class="overflow-x-auto max-h-60 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#1a1a1a] text-[#00ffc8] sticky top-0">
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

            <!-- Instant SMS Inbox -->
            <div class="wallet-subsection">
                <h3 class="text-lg font-semibold mb-4">Instant SMS Inbox</h3>
                <div class="overflow-x-auto max-h-60 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#1a1a1a] text-[#00ffc8] sticky top-0">
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

            <!-- Instant SMS Outbox -->
            <div class="wallet-subsection lg:col-span-2">
                <h3 class="text-lg font-semibold mb-4">Instant SMS Outbox</h3>
                <div class="overflow-x-auto max-h-60 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#1a1a1a] text-[#00ffc8] sticky top-0">
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

    <!-- ==================== DATA SECTION ==================== -->
    <div id="data" class="section hidden">
        <div class="section-header">
            <i data-lucide="rss" class="w-6 h-6 text-[#00ffc8]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">Data Services</h2>
            <span class="ml-auto text-xs bg-[#00ffc8]/20 text-[#00ffc8] px-3 py-1">bundles + subscriptions</span>
        </div>

        <!-- Data Bundles -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Data Bundles</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ffc8]">
                        <tr>
                            <th class="p-2 text-left">Name</th>
                            <th class="p-2 text-left">Data (MB)</th>
                            <th class="p-2 text-left">Price</th>
                            <th class="p-2 text-left">Validity Days</th>
                        </tr>
                    </thead>
                    <tbody id="bundlesList">
                        <tr><td colspan="4" class="text-center p-4 text-gray-500">No bundles found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Data Subscriptions -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Active Subscriptions</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ffc8]">
                        <tr>
                            <th class="p-2 text-left">User ID</th>
                            <th class="p-2 text-left">Bundle</th>
                            <th class="p-2 text-left">Activated</th>
                            <th class="p-2 text-left">Expires</th>
                        </tr>
                    </thead>
                    <tbody id="subscriptionsList">
                        <tr><td colspan="4" class="text-center p-4 text-gray-500">No subscriptions found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== TRANSACTIONS SECTION ==================== -->
    <div id="transactions" class="section hidden">
        <div class="section-header">
            <i data-lucide="list" class="w-6 h-6 text-[#00ffc8]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">All Transactions</h2>
        </div>

        <!-- Regular Transactions -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Transactions</h3>
            <div class="overflow-x-auto max-h-60 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ffc8] sticky top-0">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">Type</th>
                            <th class="p-2 text-left">Amount</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="transactionsList">
                        <tr><td colspan="4" class="text-center p-4 text-gray-500"><span class="loading-spinner"></span>Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cross Wallet Transfers -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Cross-Wallet Transfers</h3>
            <div class="overflow-x-auto max-h-60 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ffc8] sticky top-0">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">From</th>
                            <th class="p-2 text-left">To</th>
                            <th class="p-2 text-left">Amount</th>
                            <th class="p-2 text-left">Fee</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="crossWalletTransfersList">
                        <tr><td colspan="6" class="text-center p-4 text-gray-500">No transfers found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Instant Money -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Instant Money</h3>
            <div class="overflow-x-auto max-h-60 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ffc8] sticky top-0">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">Sender</th>
                            <th class="p-2 text-left">Recipient</th>
                            <th class="p-2 text-left">Amount</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="instantMoneyList">
                        <tr><td colspan="5" class="text-center p-4 text-gray-500">No transactions found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== AGENTS SECTION ==================== -->
    <div id="agents" class="section hidden">
        <div class="section-header">
            <i data-lucide="users" class="w-6 h-6 text-[#00ffc8]"></i>
            <h2 class="text-2xl font-semibold tracking-wider text-white">Agent Management</h2>
        </div>

        <!-- Agent Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="p-4 balance-widget">
                <span class="text-xs text-gray-400">Total Agents</span>
                <span id="totalAgents" class="text-xl font-bold block">0</span>
            </div>
            <div class="p-4 balance-widget">
                <span class="text-xs text-gray-400">Active Agents</span>
                <span id="activeAgents" class="text-xl font-bold block text-[#00ffc8]">0</span>
            </div>
            <div class="p-4 balance-widget">
                <span class="text-xs text-gray-400">KYC Verified</span>
                <span id="kycVerified" class="text-xl font-bold block">0</span>
            </div>
            <div class="p-4 balance-widget">
                <span class="text-xs text-gray-400">Total Float Limit</span>
                <span id="totalFloatLimit" class="text-xl font-bold block">BWP0.00</span>
            </div>
        </div>

        <!-- Agents List -->
        <div class="wallet-subsection">
            <h3 class="text-lg font-semibold mb-4">Agents Directory</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#1a1a1a] text-[#00ffc8]">
                        <tr>
                            <th class="p-2 text-left">Business Name</th>
                            <th class="p-2 text-left">Phone</th>
                            <th class="p-2 text-left">KYC Status</th>
                            <th class="p-2 text-left">Status</th>
                            <th class="p-2 text-left">Created</th>
                        </tr>
                    </thead>
                    <tbody id="agentsList">
                        <tr><td colspan="5" class="text-center p-4 text-gray-500">No agents found</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ MODALS ============ -->

    <!-- Notification Modal -->
    <div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-[#1a1a1a] p-6 w-96 rounded-none border border-[#00ffc8] shadow-2xl">
            <h3 id="notificationTitle" class="text-xl font-bold text-[#00ffc8] mb-3 border-b border-gray-700 pb-2">Notification</h3>
            <p id="notificationMessage" class="text-gray-300 mb-4 text-sm"></p>
            <button class="btn-action w-full py-2 text-sm" onclick="closeNotificationModal()">Close</button>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div id="transferModal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-[#1a1a1a] p-6 w-96 rounded-none border border-[#00ffc8] shadow-2xl">
            <h3 id="transferModalTitle" class="text-xl font-bold text-[#00ffc8] mb-3 border-b border-gray-700 pb-2">Transfer</h3>
            <div class="mt-4">
                <input type="number" id="transferAmount" class="input-field w-full p-3 text-sm" placeholder="Enter amount (BWP)">
                <p id="transferError" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
            <div class="flex gap-4 mt-6">
                <button id="transferSubmitBtn" class="btn-action flex-1 py-2 uppercase text-sm" onclick="processTransfer()">Submit</button>
                <button class="btn-tab flex-1 py-2 uppercase text-sm" onclick="closeTransferModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
/* Injected safe PHP vars */
const loggedInUser = <?= json_encode($loggedInUserId) ?>;
const loggedInUserPhone = <?= json_encode($loggedInUserPhone) ?>;
const isRailway = window.location.hostname.includes('railway.app') || window.location.hostname.includes('up.railway.app');
const basePath = isRailway ? '/backend/routes/api.php' : '/CazaCom/backend/routes/api.php';
const baseApiUrl = window.location.origin + basePath;
let currentTransferAction = null;

// --- Core API Call Utility ---
async function apiCall(endpoint, params = {}, method = 'GET') {
    const url = new URL(baseApiUrl);
    url.searchParams.append('path', endpoint);
    
    // Add params to URL for GET requests
    if (method === 'GET') {
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
    }
    
    const config = {
        method: method,
        headers: {
            'Accept': 'application/json'
        },
        credentials: 'include'
    };
    
    if (method !== 'GET') {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(params);
    }

    try {
        const response = await fetch(url, config);
        const text = await response.text();
        
        if (!text) {
            return { status: 'error', message: 'Empty response' };
        }
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', text.substring(0, 200));
            return { status: 'error', message: 'Invalid JSON response', raw: text.substring(0, 200) };
        }
    } catch (e) {
        console.error("API Call Error:", e);
        return { status: 'error', message: 'Network error: ' + e.message };
    }
}

// --- Notification/Modal Utilities ---
function showNotification(title, message, isError = false) {
    const modal = document.getElementById('notificationModal');
    document.getElementById('notificationTitle').innerText = title;
    document.getElementById('notificationMessage').innerText = message;
    
    const titleElement = document.getElementById('notificationTitle');
    const borderElement = modal.querySelector('div');
    
    if (isError) {
        titleElement.classList.remove('text-[#00ffc8]');
        titleElement.classList.add('text-red-500');
        borderElement.style.borderColor = '#ef4444';
    } else {
        titleElement.classList.remove('text-red-500');
        titleElement.classList.add('text-[#00ffc8]');
        borderElement.style.borderColor = '#00ffc8';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeNotificationModal() {
    document.getElementById('notificationModal').classList.remove('flex');
    document.getElementById('notificationModal').classList.add('hidden');
}

// --- UI Navigation ---
function openSection(id) {
    document.querySelectorAll('.section').forEach(sec => sec.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    
    document.querySelectorAll('.btn-tab').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`nav-${id}`).classList.add('active');

    // Load data based on section
    switch(id) {
        case 'wallet':
            loadWalletData();
            break;
        case 'mobile-money':
            loadMobileMoneyData();
            break;
        case 'calls':
            loadCallsData();
            break;
        case 'sms':
            loadSmsData();
            break;
        case 'data':
            loadDataBundles();
            break;
        case 'transactions':
            loadAllTransactions();
            break;
        case 'agents':
            loadAgentsData();
            break;
    }
}

// ============ WALLET FUNCTIONS ============
async function loadWalletData() {
    try {
        // Get wallet balance
        const balanceRes = await apiCall('wallet/balance', {}, 'GET');
        if (balanceRes.status === 'success') {
            document.getElementById('mainBalance').innerHTML = `BWP${parseFloat(balanceRes.balance || 0).toFixed(2)}`;
            document.getElementById('creditBalance').innerHTML = `BWP${parseFloat(balanceRes.credit_balance || 0).toFixed(2)}`;
            document.getElementById('saccusEwalletBalance').innerHTML = `BWP${parseFloat(balanceRes.saccus_ewallet_balance || 0).toFixed(2)}`;
        } else {
            throw new Error(balanceRes.message || 'Failed to load balance');
        }

        // Load transactions
        const txRes = await apiCall('transactions', { user_id: loggedInUser }, 'GET');
        displayWalletTransactions(txRes.transactions || []);
    } catch (error) {
        console.error('Failed to load wallet data:', error);
        document.getElementById('mainBalance').innerHTML = 'BWP0.00';
        document.getElementById('creditBalance').innerHTML = 'BWP0.00';
        document.getElementById('saccusEwalletBalance').innerHTML = 'BWP0.00';
        document.getElementById('walletTransactions').innerHTML = '<p class="text-center text-red-500 p-4">Error loading transactions</p>';
    }
}

function displayWalletTransactions(transactions) {
    const container = document.getElementById('walletTransactions');
    if (!transactions || !transactions.length) {
        container.innerHTML = '<p class="text-center text-gray-500 p-4">No transactions found</p>';
        return;
    }
    
    container.innerHTML = transactions.slice(0, 10).map(tx => `
        <div class="p-3 bg-[#1a1a1a] border border-gray-800">
            <div class="flex justify-between">
                <span class="font-medium">${tx.type}</span>
                <span class="text-[#00ffc8]">BWP ${parseFloat(tx.amount).toFixed(2)}</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">
                ${new Date(tx.created_at).toLocaleString()} | Status: ${tx.status}
            </div>
        </div>
    `).join('');
}

// ============ MOBILE MONEY FUNCTIONS ============
async function loadMobileMoneyData() {
    try {
        // Get mobile money balance
        const balanceRes = await apiCall('mm/balance', {}, 'GET');
        if (balanceRes.status === 'success') {
            document.getElementById('mobileMoneyBalance').innerHTML = `BWP${parseFloat(balanceRes.mobile_money_balance || 0).toFixed(2)}`;
            document.getElementById('mobileMoneyCreditBalance').innerHTML = `BWP${parseFloat(balanceRes.credit_balance || 0).toFixed(2)}`;
        }

        // Load mobile money transactions
        const historyRes = await apiCall('mm/history', {}, 'GET');
        displayMobileMoneyTransactions(historyRes.transactions || []);
    } catch (error) {
        console.error('Failed to load mobile money data:', error);
        document.getElementById('mobileMoneyBalance').innerHTML = 'BWP0.00';
        document.getElementById('mobileMoneyCreditBalance').innerHTML = 'BWP0.00';
        document.getElementById('mobileMoneyTransactionsList').innerHTML = '<tr><td colspan="6" class="text-center p-4 text-red-500">Error loading transactions</td></tr>';
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
            <td class="p-2">${tx.type}</td>
            <td class="p-2 ${tx.type === 'deposit' ? 'text-[#00ffc8]' : 'text-red-400'}">BWP ${parseFloat(tx.amount).toFixed(2)}</td>
            <td class="p-2">BWP ${parseFloat(tx.fee || 0).toFixed(2)}</td>
            <td class="p-2">${tx.recipient_phone || '-'}</td>
            <td class="p-2"><span class="px-2 py-1 text-xs ${tx.status === 'completed' ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-400'}">${tx.status}</span></td>
        </tr>
    `).join('');
}

// ============ CALLS FUNCTIONS ============
async function loadCallsData() {
    // This feature is not implemented yet
    document.getElementById('callsList').innerHTML = '<tr><td colspan="5" class="text-center p-4 text-gray-500">Calls feature coming soon</td></tr>';
}

// ============ SMS FUNCTIONS ============
async function loadSmsData() {
    try {
        console.log('Loading SMS data for user:', loggedInUser, 'phone:', loggedInUserPhone);
        
        // Load SMS history - this is the ONLY source of truth
        const historyRes = await apiCall('sms/history', {}, 'GET');
        console.log('SMS History Response:', historyRes);
        
        if (historyRes.status === 'success' && historyRes.history) {
            // Display all messages in the main SMS Records table
            displaySmsRecords(historyRes.history);
            
            // Calculate total cost
            const totalCost = historyRes.history.reduce((sum, sms) => sum + parseFloat(sms.cost || 0), 0);
            document.getElementById('totalSmsCost').innerHTML = `BWP ${totalCost.toFixed(2)}`;
            
            // Split messages by direction for inbox/outbox counts
            const sentMessages = historyRes.history.filter(sms => 
                sms.direction === 'sent' || sms.sender_number === loggedInUserPhone
            );
            const receivedMessages = historyRes.history.filter(sms => 
                sms.direction === 'received' || sms.target_number === loggedInUserPhone
            );
            
            document.getElementById('inboxCount').innerHTML = receivedMessages.length;
            document.getElementById('outboxCount').innerHTML = sentMessages.length;
            
            // Also populate the inbox and outbox tables with the same data
            displaySmsInbox(receivedMessages);
            displaySmsOutbox(sentMessages);
        } else {
            console.error('SMS history error:', historyRes.message);
            document.getElementById('smsRecords').innerHTML = '<tr><td colspan="4" class="text-center p-4 text-red-500">No SMS records found</td></tr>';
            document.getElementById('instantSmsInbox').innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No messages</td></tr>';
            document.getElementById('instantSmsOutbox').innerHTML = '<tr><td colspan="5" class="text-center p-4 text-gray-500">No messages</td></tr>';
            document.getElementById('totalSmsCost').innerHTML = 'BWP 0.00';
            document.getElementById('inboxCount').innerHTML = '0';
            document.getElementById('outboxCount').innerHTML = '0';
        }
    } catch (error) {
        console.error('Failed to load SMS data:', error);
        document.getElementById('smsRecords').innerHTML = '<tr><td colspan="4" class="text-center p-4 text-red-500">Error loading SMS data</td></tr>';
    }
}

function displaySmsRecords(smsRecords) {
    const tbody = document.getElementById('smsRecords');
    if (!smsRecords || !smsRecords.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No SMS records</td></tr>';
        return;
    }
    
    tbody.innerHTML = smsRecords.map(sms => {
        const target = sms.target_number || sms.recipient || 'N/A';
        const direction = sms.direction || (sms.sender_number === loggedInUserPhone ? 'sent' : 'received');
        const created = sms.created_at || sms.timestamp;
        
        return `
        <tr class="border-b border-gray-800">
            <td class="p-2">${new Date(created).toLocaleString()}</td>
            <td class="p-2">${target}</td>
            <td class="p-2">BWP ${parseFloat(sms.cost || 0).toFixed(2)}</td>
            <td class="p-2"><span class="px-2 py-1 text-xs ${direction === 'sent' ? 'bg-blue-900 text-blue-400' : 'bg-green-900 text-green-400'}">${direction}</span></td>
        </tr>
    `}).join('');
}

// Reuse the same data for inbox (received messages)
function displaySmsInbox(receivedMessages) {
    const tbody = document.getElementById('instantSmsInbox');
    if (!receivedMessages || !receivedMessages.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No received messages</td></tr>';
        return;
    }
    
    tbody.innerHTML = receivedMessages.map(msg => `
        <tr class="border-b border-gray-800">
            <td class="p-2">${msg.sender_number || 'N/A'}</td>
            <td class="p-2 max-w-xs truncate">${msg.message || ''}</td>
            <td class="p-2">${msg.created_at ? new Date(msg.created_at).toLocaleString() : 'N/A'}</td>
            <td class="p-2">${msg.cost ? 'BWP ' + parseFloat(msg.cost).toFixed(2) : '-'}</td>
        </tr>
    `).join('');
}

// Reuse the same data for outbox (sent messages)
function displaySmsOutbox(sentMessages) {
    const tbody = document.getElementById('instantSmsOutbox');
    if (!sentMessages || !sentMessages.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4 text-gray-500">No sent messages</td></tr>';
        return;
    }
    
    tbody.innerHTML = sentMessages.map(msg => `
        <tr class="border-b border-gray-800">
            <td class="p-2">${msg.target_number || msg.recipient || 'N/A'}</td>
            <td class="p-2 max-w-xs truncate">${msg.message || ''}</td>
            <td class="p-2"><span class="px-2 py-1 text-xs bg-green-900 text-green-400">delivered</span></td>
            <td class="p-2">1</td>
            <td class="p-2">${msg.created_at ? new Date(msg.created_at).toLocaleString() : 'N/A'}</td>
        </tr>
    `).join('');
}

// ============ DATA BUNDLES FUNCTIONS ============
async function loadDataBundles() {
    // This feature is not implemented yet
    document.getElementById('bundlesList').innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">Data bundles coming soon</td></tr>';
    document.getElementById('subscriptionsList').innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">Subscriptions coming soon</td></tr>';
}

// ============ TRANSACTIONS FUNCTIONS ============
async function loadAllTransactions() {
    try {
        const txRes = await apiCall('transactions', { user_id: loggedInUser }, 'GET');
        displayTransactions(txRes.transactions || []);
    } catch (error) {
        console.error('Failed to load transactions:', error);
        document.getElementById('transactionsList').innerHTML = '<tr><td colspan="4" class="text-center p-4 text-red-500">Error loading transactions</td></tr>';
    }
}

function displayTransactions(transactions) {
    const tbody = document.getElementById('transactionsList');
    if (!transactions || !transactions.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No transactions found</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactions.map(tx => `
        <tr class="border-b border-gray-800">
            <td class="p-2">${new Date(tx.created_at).toLocaleString()}</td>
            <td class="p-2">${tx.type}</td>
            <td class="p-2 ${parseFloat(tx.amount) > 0 ? 'text-[#00ffc8]' : 'text-red-400'}">BWP ${parseFloat(tx.amount).toFixed(2)}</td>
            <td class="p-2"><span class="px-2 py-1 text-xs ${tx.status === 'success' ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-400'}">${tx.status}</span></td>
        </tr>
    `).join('');
}

// ============ AGENTS FUNCTIONS ============
async function loadAgentsData() {
    // This feature is not implemented yet
    document.getElementById('agentsList').innerHTML = '<tr><td colspan="5" class="text-center p-4 text-gray-500">Agents feature coming soon</td></tr>';
}

// ============ TRANSFER MODAL FUNCTIONS ============
function openTransferModal(action) {
    currentTransferAction = action;
    const titleMap = {
        'wallet_to_saccus': 'Main Balance → Saccus eWallet',
        'saccus_to_wallet': 'Saccus eWallet → Main Balance',
        'credit_to_balance': 'Credit → Main Balance',
        'balance_to_credit': 'Main Balance → Credit'
    };
    
    document.getElementById('transferModalTitle').innerText = titleMap[action] || 'Transfer';
    document.getElementById('transferAmount').value = '';
    document.getElementById('transferError').classList.add('hidden');
    document.getElementById('transferModal').classList.remove('hidden');
    document.getElementById('transferModal').classList.add('flex');
}

function closeTransferModal() {
    document.getElementById('transferModal').classList.remove('flex');
    document.getElementById('transferModal').classList.add('hidden');
}

async function processTransfer() {
    const amountInput = document.getElementById('transferAmount').value;
    const errorEl = document.getElementById('transferError');
    const submitBtn = document.getElementById('transferSubmitBtn');
    
    if (!amountInput || isNaN(amountInput) || parseFloat(amountInput) <= 0) {
        errorEl.innerText = 'Please enter a valid amount';
        errorEl.classList.remove('hidden');
        return;
    }
    
    const amount = parseFloat(amountInput);
    errorEl.classList.add('hidden');
    submitBtn.disabled = true;
    submitBtn.innerText = 'Processing...';
    
    // Map actions to actual API endpoints
    let endpoint = '';
    let payload = { amount: amount };
    
    switch(currentTransferAction) {
        case 'wallet_to_saccus':
            endpoint = 'wallet/balance_to_ewallet';
            break;
        case 'saccus_to_wallet':
            endpoint = 'wallet/ewallet_to_balance';
            payload.user_id = loggedInUser;
            payload.phone = loggedInUserPhone;
            break;
        case 'credit_to_balance':
            endpoint = 'wallet/credit_to_balance';
            break;
        case 'balance_to_credit':
            endpoint = 'wallet/balance_to_credit';
            break;
        default:
            showNotification("Error", "Unknown transfer type", true);
            submitBtn.disabled = false;
            submitBtn.innerText = 'Submit';
            closeTransferModal();
            return;
    }
    
    const res = await apiCall(endpoint, payload, 'POST');
    
    submitBtn.disabled = false;
    submitBtn.innerText = 'Submit';
    
    if (res.status === 'success') {
        showNotification("Success", `Transfer of BWP ${amount.toFixed(2)} completed`, false);
        closeTransferModal();
        loadWalletData();
    } else {
        showNotification("Error", res.message || "Transfer failed", true);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        lucide.createIcons();
    }
    openSection('wallet');
});
</script>
</body>
</html>




