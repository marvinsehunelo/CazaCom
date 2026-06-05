--
-- PostgreSQL Schema for 'cazacom' Database
-- Generated from MySQL schema provided by user
--

----------------------------------------------------------------------------------------------------
-- 1. Core User & Wallet Tables
----------------------------------------------------------------------------------------------------

-- Table: users
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255),
    password_hash VARCHAR(255) NOT NULL,
    pin CHAR(4) NOT NULL, -- Assuming PIN is exactly 4 characters
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: wallets
CREATE TABLE wallets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE, -- Assuming one wallet per user
    balance NUMERIC(15, 2) DEFAULT 0.00,
    last_updated TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    saccus_ewallet_balance NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
    credit_balance NUMERIC(15, 2) NOT NULL DEFAULT 0.00
);

-- Table: sessions
CREATE TABLE sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    last_activity TIMESTAMP WITHOUT TIME ZONE
);

----------------------------------------------------------------------------------------------------
-- 2. API & Settings
----------------------------------------------------------------------------------------------------

-- Table: api_keys
CREATE TABLE api_keys (
    id SERIAL PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL UNIQUE,
    permissions TEXT, -- LONGTEXT converted to TEXT
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP WITHOUT TIME ZONE
);

-- Table: settings
CREATE TABLE settings (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT -- TEXT/LONGTEXT converted to TEXT
);

----------------------------------------------------------------------------------------------------
-- 3. Telco Services (Calls, SMS, Data)
----------------------------------------------------------------------------------------------------

-- Table: bundles
CREATE TABLE bundles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    data_mb INTEGER NOT NULL,
    price NUMERIC(15, 2) NOT NULL,
    validity_days INTEGER NOT NULL
);

-- Table: calls
CREATE TABLE calls (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    target_number VARCHAR(255) NOT NULL,
    duration INTEGER NOT NULL,
    cost NUMERIC(15, 2) NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: sms
CREATE TABLE sms (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    target_number VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    cost NUMERIC(15, 2) NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sender_number VARCHAR(255),
    direction VARCHAR(50) NOT NULL -- ENUM converted to VARCHAR
);

-- Table: subscriptions
CREATE TABLE subscriptions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    bundle_id INTEGER NOT NULL,
    expiry_date DATE NOT NULL
);

-- Table: data_subscriptions
CREATE TABLE data_subscriptions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    bundle_name VARCHAR(255),
    activated_at TIMESTAMP WITHOUT TIME ZONE,
    expires_at TIMESTAMP WITHOUT TIME ZONE
);

----------------------------------------------------------------------------------------------------
-- 4. Instant Messaging (SMS Inbox/Outbox)
----------------------------------------------------------------------------------------------------

-- Table: instant_sms_inbox
CREATE TABLE instant_sms_inbox (
    id BIGSERIAL PRIMARY KEY, -- BIGINT converted to BIGSERIAL
    provider VARCHAR(255),
    from_phone VARCHAR(255),
    to_phone VARCHAR(255),
    message TEXT,
    received_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    parsed_at TIMESTAMP WITHOUT TIME ZONE,
    parse_result TEXT, -- LONGTEXT converted to TEXT
    processed BOOLEAN DEFAULT FALSE, -- TINYINT(1) converted to BOOLEAN
    raw_payload TEXT -- LONGTEXT converted to TEXT
);

-- Table: instant_sms_outbox
CREATE TABLE instant_sms_outbox (
    id BIGSERIAL PRIMARY KEY,
    provider VARCHAR(255) DEFAULT 'gateway',
    to_phone VARCHAR(255) NOT NULL,
    from_phone VARCHAR(255),
    message TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'queued', -- ENUM converted to VARCHAR
    attempts INTEGER DEFAULT 0,
    last_attempt_at TIMESTAMP WITHOUT TIME ZONE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_error TEXT,
    related_transfer_id BIGINT
);

----------------------------------------------------------------------------------------------------
-- 5. Transactions & Transfers
----------------------------------------------------------------------------------------------------

-- Table: transactions
CREATE TABLE transactions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    type VARCHAR(50) NOT NULL, -- ENUM converted to VARCHAR
    amount NUMERIC(15, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'success', -- ENUM converted to VARCHAR
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: instant_money
CREATE TABLE instant_money (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER NOT NULL,
    sender_phone VARCHAR(255) NOT NULL,
    recipient_phone VARCHAR(255) NOT NULL,
    amount NUMERIC(15, 2) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(50) DEFAULT 'pending', -- ENUM converted to VARCHAR
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    redeemed_at TIMESTAMP WITHOUT TIME ZONE
);

-- Table: instant_money_transactions
CREATE TABLE instant_money_transactions (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER NOT NULL,
    recipient_phone VARCHAR(255) NOT NULL,
    voucher_code VARCHAR(255) NOT NULL,
    pin_code VARCHAR(255) NOT NULL,
    amount NUMERIC(15, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'PENDING', -- ENUM converted to VARCHAR
    channel VARCHAR(50) DEFAULT 'DASHBOARD', -- ENUM converted to VARCHAR
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    redeemed_at TIMESTAMP WITHOUT TIME ZONE
);

-- Table: instant_transfers
CREATE TABLE instant_transfers (
    id BIGSERIAL PRIMARY KEY,
    external_id VARCHAR(255),
    token VARCHAR(255),
    source_bank_code VARCHAR(255),
    payer_phone VARCHAR(255),
    payee_phone VARCHAR(255) NOT NULL,
    amount NUMERIC(15, 2) NOT NULL,
    fee NUMERIC(15, 2) DEFAULT 0.00,
    commission NUMERIC(15, 2) DEFAULT 0.00,
    currency CHAR(3) DEFAULT 'BWP',
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- ENUM converted to VARCHAR
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP WITHOUT TIME ZONE,
    raw_payload TEXT, -- LONGTEXT converted to TEXT
    processed_by VARCHAR(255),
    reversal_of BIGINT,
    notes TEXT
);

-- Table: transfer_tokens
CREATE TABLE transfer_tokens (
    id BIGSERIAL PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    external_id VARCHAR(255),
    payer_phone VARCHAR(255),
    payee_phone VARCHAR(255),
    amount NUMERIC(15, 2),
    currency CHAR(3) DEFAULT 'BWP',
    expires_at TIMESTAMP WITHOUT TIME ZONE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consumed BOOLEAN DEFAULT FALSE, -- TINYINT(1) converted to BOOLEAN
    consumed_at TIMESTAMP WITHOUT TIME ZONE,
    consumed_by INTEGER
);

-- Table: cazacom_middleman
CREATE TABLE cazacom_middleman (
    id SERIAL PRIMARY KEY,
    phone_number VARCHAR(255) NOT NULL UNIQUE,
    api_key VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

----------------------------------------------------------------------------------------------------
-- 6. Foreign Key Constraints (Recommended for Data Integrity)
----------------------------------------------------------------------------------------------------

-- Wallets
ALTER TABLE wallets ADD CONSTRAINT fk_wallets_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- Sessions
ALTER TABLE sessions ADD CONSTRAINT fk_sessions_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- Calls
ALTER TABLE calls ADD CONSTRAINT fk_calls_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- SMS
ALTER TABLE sms ADD CONSTRAINT fk_sms_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- Subscriptions
ALTER TABLE subscriptions ADD CONSTRAINT fk_subs_user_id FOREIGN KEY (user_id) REFERENCES users(id);
ALTER TABLE subscriptions ADD CONSTRAINT fk_subs_bundle_id FOREIGN KEY (bundle_id) REFERENCES bundles(id);

-- Data Subscriptions
ALTER TABLE data_subscriptions ADD CONSTRAINT fk_data_subs_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- Transactions
ALTER TABLE transactions ADD CONSTRAINT fk_transactions_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- Instant Money
ALTER TABLE instant_money ADD CONSTRAINT fk_im_sender_id FOREIGN KEY (sender_id) REFERENCES users(id);

-- Instant Money Transactions
ALTER TABLE instant_money_transactions ADD CONSTRAINT fk_imt_sender_id FOREIGN KEY (sender_id) REFERENCES users(id);

-- Instant SMS Outbox
ALTER TABLE instant_sms_outbox ADD CONSTRAINT fk_iso_related_transfer_id FOREIGN KEY (related_transfer_id) REFERENCES instant_transfers(id);

-- Transfer Tokens
ALTER TABLE transfer_tokens ADD CONSTRAINT fk_tt_consumed_by FOREIGN KEY (consumed_by) REFERENCES users(id);

-- Chart of accounts
CREATE TABLE ledger_accounts (
    id SERIAL PRIMARY KEY,
    account_code VARCHAR(50) UNIQUE NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    account_type VARCHAR(50) NOT NULL, -- ASSET, LIABILITY, REVENUE, EXPENSE, SAFEGUARDING
    owner_type VARCHAR(50), -- USER, AGENT, SYSTEM, TRUST
    owner_id INTEGER,
    currency CHAR(3) DEFAULT 'BWP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Every money movement must have debit + credit
CREATE TABLE ledger_entries (
    id BIGSERIAL PRIMARY KEY,
    debit_account INTEGER NOT NULL,
    credit_account INTEGER NOT NULL,
    amount NUMERIC(20,2) NOT NULL CHECK (amount > 0),
    reference_type VARCHAR(50), -- CASHIN, TRANSFER, WITHDRAWAL
    reference_id BIGINT,
    narration TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE wallet_accounts (
    user_id INTEGER PRIMARY KEY,
    ledger_account_id INTEGER UNIQUE NOT NULL
);

CREATE TABLE trust_reconciliation (
    id BIGSERIAL PRIMARY KEY,
    bank_reported_balance NUMERIC(20,2),
    emoney_liability NUMERIC(20,2),
    variance NUMERIC(20,2),
    status VARCHAR(20), -- MATCH / BREACH
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE agents (
    id SERIAL PRIMARY KEY,
    business_name VARCHAR(255),
    phone_number VARCHAR(50),
    kyc_status VARCHAR(20),
    status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE agent_float_accounts (
    id SERIAL PRIMARY KEY,
    agent_id INTEGER UNIQUE,
    ledger_account_id INTEGER UNIQUE,
    float_limit NUMERIC(20,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE agent_float_movements (
    id BIGSERIAL PRIMARY KEY,
    agent_id INTEGER,
    amount NUMERIC(20,2),
    direction VARCHAR(10), -- IN / OUT
    method VARCHAR(50), -- bank deposit, super-agent
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE aml_flags (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER,
    transaction_ref BIGINT,
    risk_level VARCHAR(20),
    reason TEXT,
    reported BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE suspicious_activity_reports (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER,
    aggregated_amount NUMERIC(20,2),
    monitoring_period_days INTEGER,
    status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE settlement_positions (
    id BIGSERIAL PRIMARY KEY,
    counterparty VARCHAR(100),
    net_amount NUMERIC(20,2),
    cycle_date DATE,
    status VARCHAR(20) -- PENDING / SETTLED
);

CREATE TABLE settlement_transactions (
    id BIGSERIAL PRIMARY KEY,
    position_id BIGINT,
    bank_reference VARCHAR(100),
    settled_amount NUMERIC(20,2),
    settled_at TIMESTAMP
);

CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    actor_type VARCHAR(50),
    actor_id INTEGER,
    action VARCHAR(100),
    entity VARCHAR(100),
    record_id BIGINT,
    old_data JSONB,
    new_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO agents (business_name, phone_number, kyc_status, status)
VALUES ('SmartShop', '+26771234567', 'pending', 'active');

INSERT INTO agent_float_accounts (agent_id, ledger_account_id, float_limit)
VALUES (1, 101, 5000.00);

-- Drop old table if exists
DROP TABLE IF EXISTS mobile_money_accounts;

-- Mobile Money Accounts Table
CREATE TABLE mobile_money_accounts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE, -- one MM account per user
    balance NUMERIC(20,2) DEFAULT 0.00, -- customer balance
    credit_balance NUMERIC(20,2) DEFAULT 0.00, -- for internal operations if needed
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create ledger accounts for Alice and Bob's mobile money accounts
INSERT INTO ledger_accounts (account_code, account_name, account_type, owner_type, owner_id)
SELECT 'MM-' || id, 'Mobile Money Account of ' || name, 'LIABILITY', 'USER', id
FROM users
WHERE phone_number IN ('+26770000000', '+26770000001');

-- Link mobile money accounts to ledger accounts
CREATE TABLE mobile_money_account_ledgers (
    user_id INTEGER PRIMARY KEY,
    ledger_account_id INTEGER UNIQUE NOT NULL
);

INSERT INTO mobile_money_account_ledgers (user_id, ledger_account_id)
SELECT u.id, l.id
FROM users u
JOIN ledger_accounts l ON l.owner_type = 'USER' AND l.owner_id = u.id
WHERE u.phone_number IN ('+26770000000', '+26770000001');

-- Initialize mobile money account balances
INSERT INTO mobile_money_accounts (user_id, balance, credit_balance)
SELECT id, 1000.00, 1000.00
FROM users
WHERE phone_number IN ('+26770000000', '+26770000001');

ALTER TABLE mobile_money_account_ledgers
    ADD CONSTRAINT fk_mm_ledger_user FOREIGN KEY (user_id) REFERENCES users(id),
    ADD CONSTRAINT fk_mm_ledger_account FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts(id);

-- ============================================
-- MOBILE MONEY TRANSACTIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS mobile_money_transactions (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    type VARCHAR(50) NOT NULL, -- deposit, withdraw, transfer, airtime, cross_wallet
    amount NUMERIC(20,2) NOT NULL,
    fee NUMERIC(10,2) DEFAULT 0.00,
    reference VARCHAR(255) UNIQUE,
    recipient_phone VARCHAR(50),
    network VARCHAR(20), -- mascom, orange, btcl
    status VARCHAR(20) DEFAULT 'pending', -- pending, completed, failed
    wallet_type VARCHAR(20), -- saccus, main, credit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

-- ============================================
-- CROSS WALLET TRANSFERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS cross_wallet_transfers (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    source_wallet VARCHAR(50) NOT NULL, -- saccus, main, credit, mobile_money
    destination_wallet VARCHAR(50) NOT NULL, -- saccus, main, credit, mobile_money
    amount NUMERIC(20,2) NOT NULL,
    fee NUMERIC(10,2) DEFAULT 0.00,
    reference VARCHAR(255) UNIQUE,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

-- Add indexes for performance
CREATE INDEX idx_mm_transactions_user_id ON mobile_money_transactions(user_id);
CREATE INDEX idx_mm_transactions_status ON mobile_money_transactions(status);
CREATE INDEX idx_cross_wallet_user_id ON cross_wallet_transfers(user_id);

-- Add missing tables for complete functionality

-- 1. AirTime Purchases (referenced in dashboard)
CREATE TABLE IF NOT EXISTS airtime_purchases (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    phone_number VARCHAR(20) NOT NULL,
    amount NUMERIC NOT NULL,
    network VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

-- 2. Mobile Money Transfers (specific tracking)
CREATE TABLE IF NOT EXISTS mobile_money_transfers (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    recipient_phone VARCHAR(20) NOT NULL,
    amount NUMERIC NOT NULL,
    fee NUMERIC DEFAULT 0,
    network VARCHAR(20),
    status VARCHAR(20) DEFAULT 'pending',
    reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

-- 3. API Endpoints Configuration
CREATE TABLE IF NOT EXISTS api_endpoints (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_mobile_money_accounts_user_id ON mobile_money_accounts(user_id);
CREATE INDEX IF NOT EXISTS idx_mobile_money_transactions_user_id ON mobile_money_transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_instant_sms_inbox_from_phone ON instant_sms_inbox(from_phone);
CREATE INDEX IF NOT EXISTS idx_instant_sms_outbox_to_phone ON instant_sms_outbox(to_phone);

-- 5. Fix relationship between wallets and ledger accounts
ALTER TABLE wallets 
ADD COLUMN IF NOT EXISTS ledger_account_id INTEGER REFERENCES ledger_accounts(id);

-- 6. Add wallet type tracking
ALTER TABLE wallets 
ADD COLUMN IF NOT EXISTS wallet_type VARCHAR(50) DEFAULT 'main';

-- 7. Add missing indexes for foreign keys
CREATE INDEX IF NOT EXISTS idx_agent_float_accounts_agent_id ON agent_float_accounts(agent_id);
CREATE INDEX IF NOT EXISTS idx_agent_float_accounts_ledger_account_id ON agent_float_accounts(ledger_account_id);
CREATE INDEX IF NOT EXISTS idx_agent_float_movements_agent_id ON agent_float_movements(agent_id);

CREATE VIEW vw_sms_history AS
SELECT 
    'inbound' as direction,
    from_phone as sender,
    to_phone as recipient,
    message,
    received_at as created_at
FROM instant_sms_inbox
UNION ALL
SELECT 
    'outbound' as direction,
    from_phone as sender,
    to_phone as recipient,
    message,
    created_at
FROM instant_sms_outbox;


CREATE TABLE IF NOT EXISTS settlement_transactions (
    id BIGSERIAL PRIMARY KEY,
    reference VARCHAR(100) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id),
    amount NUMERIC(20,2) NOT NULL,
    type VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    external_reference VARCHAR(100),
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS internal_transfers (
    id BIGSERIAL PRIMARY KEY,
    reference VARCHAR(100) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id),
    transfer_type VARCHAR(50) NOT NULL,
    amount NUMERIC(20,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS alerts (
    id BIGSERIAL PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS oauth_clients (
    id SERIAL PRIMARY KEY,
    client_id VARCHAR(100) UNIQUE NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    redirect_uri TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oauth_access_tokens (
    id SERIAL PRIMARY KEY,
    token VARCHAR(255) UNIQUE NOT NULL,
    client_id INTEGER REFERENCES oauth_clients(id),
    user_id INTEGER REFERENCES users(id),
    scope TEXT,
    expires_at TIMESTAMP NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    revoked_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
    id SERIAL PRIMARY KEY,
    token VARCHAR(255) UNIQUE NOT NULL,
    access_token VARCHAR(255) REFERENCES oauth_access_tokens(token),
    client_id INTEGER REFERENCES oauth_clients(id),
    user_id INTEGER REFERENCES users(id),
    expires_at TIMESTAMP NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    revoked_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
    id SERIAL PRIMARY KEY,
    code VARCHAR(255) UNIQUE NOT NULL,
    client_id INTEGER REFERENCES oauth_clients(id),
    user_id INTEGER REFERENCES users(id),
    redirect_uri TEXT,
    scope TEXT,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS financial_holds (
    id BIGSERIAL PRIMARY KEY,
    hold_reference VARCHAR(100) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id),
    amount NUMERIC(20,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'HELD',
    expires_at TIMESTAMP NOT NULL,
    source_reference VARCHAR(100),
    destination TEXT,
    release_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP,
    committed_at TIMESTAMP
);

REATE TABLE IF NOT EXISTS cashout_tokens (
    id BIGSERIAL PRIMARY KEY,
    token_reference VARCHAR(100) UNIQUE NOT NULL,
    beneficiary_phone VARCHAR(20) NOT NULL,
    amount NUMERIC(20,2) NOT NULL,
    pin_code VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    source_reference VARCHAR(100),
    status VARCHAR(20) DEFAULT 'ACTIVE',
    dispensed_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settlement_obligations (
    id BIGSERIAL PRIMARY KEY,
    reference VARCHAR(100) UNIQUE NOT NULL,
    from_participant VARCHAR(50) NOT NULL,
    to_participant VARCHAR(50) NOT NULL,
    amount NUMERIC(20,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    settled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS encryption_keys (
    id SERIAL PRIMARY KEY,
    key_id VARCHAR(100) NOT NULL,
    key_value TEXT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    retired_at TIMESTAMP
);


CREATE TABLE IF NOT EXISTS vault_kv_store (
  parent_path TEXT COLLATE "C" NOT NULL,
  path        TEXT COLLATE "C",
  key         TEXT COLLATE "C",
  value       BYTEA,
  CONSTRAINT pkey PRIMARY KEY (path, key)
);


CREATE INDEX IF NOT EXISTS parent_path_idx ON vault_kv_store (parent_path);

CREATE TABLE IF NOT EXISTS vault_ha_locks (
  ha_key      TEXT COLLATE "C" NOT NULL,
  ha_identity TEXT COLLATE "C" NOT NULL,
  ha_value    TEXT COLLATE "C",
  valid_until TIMESTAMP WITH TIME ZONE NOT NULL,
  CONSTRAINT ha_key PRIMARY KEY (ha_key)
);
