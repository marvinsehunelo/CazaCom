--
-- PostgreSQL database dump
--

\restrict 23UlbChM9mJPyXcjgaNRfJKjZHRQYScZlkqEvY4kg8E0veea86fsgIOkKhDUj4W

-- Dumped from database version 16.11 (Ubuntu 16.11-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.11 (Ubuntu 16.11-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: agent_float_accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.agent_float_accounts (
    id integer NOT NULL,
    agent_id integer,
    ledger_account_id integer,
    float_limit numeric(20,2),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.agent_float_accounts OWNER TO postgres;

--
-- Name: agent_float_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.agent_float_accounts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_float_accounts_id_seq OWNER TO postgres;

--
-- Name: agent_float_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.agent_float_accounts_id_seq OWNED BY public.agent_float_accounts.id;


--
-- Name: agent_float_movements; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.agent_float_movements (
    id bigint NOT NULL,
    agent_id integer,
    amount numeric(20,2),
    direction character varying(10),
    method character varying(50),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.agent_float_movements OWNER TO postgres;

--
-- Name: agent_float_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.agent_float_movements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_float_movements_id_seq OWNER TO postgres;

--
-- Name: agent_float_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.agent_float_movements_id_seq OWNED BY public.agent_float_movements.id;


--
-- Name: agents; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.agents (
    id integer NOT NULL,
    business_name character varying(255),
    phone_number character varying(50),
    kyc_status character varying(20),
    status character varying(20),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.agents OWNER TO postgres;

--
-- Name: agents_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.agents_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agents_id_seq OWNER TO postgres;

--
-- Name: agents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.agents_id_seq OWNED BY public.agents.id;


--
-- Name: airtime_purchases; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.airtime_purchases (
    id bigint NOT NULL,
    user_id integer NOT NULL,
    phone_number character varying(20) NOT NULL,
    amount numeric NOT NULL,
    network character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    reference character varying(100),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone
);


ALTER TABLE public.airtime_purchases OWNER TO postgres;

--
-- Name: airtime_purchases_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.airtime_purchases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.airtime_purchases_id_seq OWNER TO postgres;

--
-- Name: airtime_purchases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.airtime_purchases_id_seq OWNED BY public.airtime_purchases.id;


--
-- Name: aml_flags; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.aml_flags (
    id bigint NOT NULL,
    user_id integer,
    transaction_ref bigint,
    risk_level character varying(20),
    reason text,
    reported boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.aml_flags OWNER TO postgres;

--
-- Name: aml_flags_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.aml_flags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.aml_flags_id_seq OWNER TO postgres;

--
-- Name: aml_flags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.aml_flags_id_seq OWNED BY public.aml_flags.id;


--
-- Name: api_endpoints; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.api_endpoints (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    endpoint character varying(255) NOT NULL,
    method character varying(10) NOT NULL,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.api_endpoints OWNER TO postgres;

--
-- Name: api_endpoints_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.api_endpoints_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.api_endpoints_id_seq OWNER TO postgres;

--
-- Name: api_endpoints_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.api_endpoints_id_seq OWNED BY public.api_endpoints.id;


--
-- Name: api_keys; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.api_keys (
    id integer NOT NULL,
    client_name character varying(255) NOT NULL,
    api_key character varying(255) NOT NULL,
    permissions text,
    status character varying(50) DEFAULT 'active'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    last_used_at timestamp without time zone
);


ALTER TABLE public.api_keys OWNER TO postgres;

--
-- Name: api_keys_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.api_keys_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.api_keys_id_seq OWNER TO postgres;

--
-- Name: api_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.api_keys_id_seq OWNED BY public.api_keys.id;


--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    actor_type character varying(50),
    actor_id integer,
    action character varying(100),
    entity character varying(100),
    record_id bigint,
    old_data jsonb,
    new_data jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.audit_logs OWNER TO postgres;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.audit_logs_id_seq OWNER TO postgres;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: bundles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.bundles (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    data_mb integer NOT NULL,
    price numeric(15,2) NOT NULL,
    validity_days integer NOT NULL
);


ALTER TABLE public.bundles OWNER TO postgres;

--
-- Name: bundles_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.bundles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bundles_id_seq OWNER TO postgres;

--
-- Name: bundles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.bundles_id_seq OWNED BY public.bundles.id;


--
-- Name: calls; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.calls (
    id integer NOT NULL,
    user_id integer NOT NULL,
    target_number character varying(255) NOT NULL,
    duration integer NOT NULL,
    cost numeric(15,2) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.calls OWNER TO postgres;

--
-- Name: calls_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.calls_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.calls_id_seq OWNER TO postgres;

--
-- Name: calls_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.calls_id_seq OWNED BY public.calls.id;


--
-- Name: cazacom_middleman; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cazacom_middleman (
    id integer NOT NULL,
    phone_number character varying(255) NOT NULL,
    api_key character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.cazacom_middleman OWNER TO postgres;

--
-- Name: cazacom_middleman_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cazacom_middleman_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cazacom_middleman_id_seq OWNER TO postgres;

--
-- Name: cazacom_middleman_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cazacom_middleman_id_seq OWNED BY public.cazacom_middleman.id;


--
-- Name: cross_wallet_transfers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cross_wallet_transfers (
    id bigint NOT NULL,
    user_id integer NOT NULL,
    source_wallet character varying(50) NOT NULL,
    destination_wallet character varying(50) NOT NULL,
    amount numeric(20,2) NOT NULL,
    fee numeric(10,2) DEFAULT 0.00,
    reference character varying(255),
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone
);


ALTER TABLE public.cross_wallet_transfers OWNER TO postgres;

--
-- Name: cross_wallet_transfers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cross_wallet_transfers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cross_wallet_transfers_id_seq OWNER TO postgres;

--
-- Name: cross_wallet_transfers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cross_wallet_transfers_id_seq OWNED BY public.cross_wallet_transfers.id;


--
-- Name: data_subscriptions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_subscriptions (
    id integer NOT NULL,
    user_id integer NOT NULL,
    bundle_name character varying(255),
    activated_at timestamp without time zone,
    expires_at timestamp without time zone
);


ALTER TABLE public.data_subscriptions OWNER TO postgres;

--
-- Name: data_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_subscriptions_id_seq OWNER TO postgres;

--
-- Name: data_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_subscriptions_id_seq OWNED BY public.data_subscriptions.id;


--
-- Name: instant_money; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.instant_money (
    id integer NOT NULL,
    sender_id integer NOT NULL,
    sender_phone character varying(255) NOT NULL,
    recipient_phone character varying(255) NOT NULL,
    amount numeric(15,2) NOT NULL,
    token character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    redeemed_at timestamp without time zone,
    trust_account_id integer
);


ALTER TABLE public.instant_money OWNER TO postgres;

--
-- Name: instant_money_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.instant_money_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.instant_money_id_seq OWNER TO postgres;

--
-- Name: instant_money_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.instant_money_id_seq OWNED BY public.instant_money.id;


--
-- Name: instant_money_transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.instant_money_transactions (
    id integer NOT NULL,
    sender_id integer NOT NULL,
    recipient_phone character varying(255) NOT NULL,
    voucher_code character varying(255) NOT NULL,
    pin_code character varying(255) NOT NULL,
    amount numeric(15,2) NOT NULL,
    status character varying(50) DEFAULT 'PENDING'::character varying,
    channel character varying(50) DEFAULT 'DASHBOARD'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    redeemed_at timestamp without time zone
);


ALTER TABLE public.instant_money_transactions OWNER TO postgres;

--
-- Name: instant_money_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.instant_money_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.instant_money_transactions_id_seq OWNER TO postgres;

--
-- Name: instant_money_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.instant_money_transactions_id_seq OWNED BY public.instant_money_transactions.id;


--
-- Name: instant_sms_inbox; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.instant_sms_inbox (
    id bigint NOT NULL,
    provider character varying(255),
    from_phone character varying(255),
    to_phone character varying(255),
    message text,
    received_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    parsed_at timestamp without time zone,
    parse_result text,
    processed boolean DEFAULT false,
    raw_payload text
);


ALTER TABLE public.instant_sms_inbox OWNER TO postgres;

--
-- Name: instant_sms_inbox_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.instant_sms_inbox_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.instant_sms_inbox_id_seq OWNER TO postgres;

--
-- Name: instant_sms_inbox_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.instant_sms_inbox_id_seq OWNED BY public.instant_sms_inbox.id;


--
-- Name: instant_sms_outbox; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.instant_sms_outbox (
    id bigint NOT NULL,
    provider character varying(255) DEFAULT 'gateway'::character varying,
    to_phone character varying(255) NOT NULL,
    from_phone character varying(255),
    message text NOT NULL,
    status character varying(50) DEFAULT 'queued'::character varying,
    attempts integer DEFAULT 0,
    last_attempt_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_error text,
    related_transfer_id bigint
);


ALTER TABLE public.instant_sms_outbox OWNER TO postgres;

--
-- Name: instant_sms_outbox_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.instant_sms_outbox_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.instant_sms_outbox_id_seq OWNER TO postgres;

--
-- Name: instant_sms_outbox_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.instant_sms_outbox_id_seq OWNED BY public.instant_sms_outbox.id;


--
-- Name: instant_transfers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.instant_transfers (
    id bigint NOT NULL,
    external_id character varying(255),
    token character varying(255),
    source_bank_code character varying(255),
    payer_phone character varying(255),
    payee_phone character varying(255) NOT NULL,
    amount numeric(15,2) NOT NULL,
    fee numeric(15,2) DEFAULT 0.00,
    commission numeric(15,2) DEFAULT 0.00,
    currency character(3) DEFAULT 'BWP'::bpchar,
    status character varying(50) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    processed_at timestamp without time zone,
    raw_payload text,
    processed_by character varying(255),
    reversal_of bigint,
    notes text
);


ALTER TABLE public.instant_transfers OWNER TO postgres;

--
-- Name: instant_transfers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.instant_transfers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.instant_transfers_id_seq OWNER TO postgres;

--
-- Name: instant_transfers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.instant_transfers_id_seq OWNED BY public.instant_transfers.id;


--
-- Name: ledger_accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ledger_accounts (
    id integer NOT NULL,
    account_code character varying(50) NOT NULL,
    account_name character varying(255) NOT NULL,
    account_type character varying(50) NOT NULL,
    owner_type character varying(50),
    owner_id integer,
    currency character(3) DEFAULT 'BWP'::bpchar,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.ledger_accounts OWNER TO postgres;

--
-- Name: ledger_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ledger_accounts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ledger_accounts_id_seq OWNER TO postgres;

--
-- Name: ledger_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ledger_accounts_id_seq OWNED BY public.ledger_accounts.id;


--
-- Name: ledger_entries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ledger_entries (
    id bigint NOT NULL,
    debit_account integer NOT NULL,
    credit_account integer NOT NULL,
    amount numeric(20,2) NOT NULL,
    reference_type character varying(50),
    reference_id bigint,
    narration text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ledger_entries_amount_check CHECK ((amount > (0)::numeric))
);


ALTER TABLE public.ledger_entries OWNER TO postgres;

--
-- Name: ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ledger_entries_id_seq OWNER TO postgres;

--
-- Name: ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ledger_entries_id_seq OWNED BY public.ledger_entries.id;


--
-- Name: mno_ledger; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mno_ledger (
    id bigint NOT NULL,
    reference character varying(255) NOT NULL,
    debit_account character varying(64) NOT NULL,
    credit_account character varying(64) NOT NULL,
    amount numeric(20,4) NOT NULL,
    currency character(3) DEFAULT 'BWP'::bpchar,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.mno_ledger OWNER TO postgres;

--
-- Name: mno_ledger_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mno_ledger_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mno_ledger_id_seq OWNER TO postgres;

--
-- Name: mno_ledger_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mno_ledger_id_seq OWNED BY public.mno_ledger.id;


--
-- Name: mno_trust_accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mno_trust_accounts (
    id integer NOT NULL,
    bank_name character varying(255) NOT NULL,
    account_number character varying(64) NOT NULL,
    currency character(3) DEFAULT 'BWP'::bpchar,
    balance numeric(20,4) DEFAULT 0.0000 NOT NULL,
    last_reconciled_at timestamp without time zone
);


ALTER TABLE public.mno_trust_accounts OWNER TO postgres;

--
-- Name: mno_trust_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mno_trust_accounts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mno_trust_accounts_id_seq OWNER TO postgres;

--
-- Name: mno_trust_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mno_trust_accounts_id_seq OWNED BY public.mno_trust_accounts.id;


--
-- Name: mobile_money_account_ledgers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mobile_money_account_ledgers (
    user_id integer NOT NULL,
    ledger_account_id integer NOT NULL
);


ALTER TABLE public.mobile_money_account_ledgers OWNER TO postgres;

--
-- Name: mobile_money_accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mobile_money_accounts (
    id integer NOT NULL,
    user_id integer NOT NULL,
    balance numeric(20,2) DEFAULT 0.00,
    credit_balance numeric(20,2) DEFAULT 0.00,
    last_updated timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.mobile_money_accounts OWNER TO postgres;

--
-- Name: mobile_money_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mobile_money_accounts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mobile_money_accounts_id_seq OWNER TO postgres;

--
-- Name: mobile_money_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mobile_money_accounts_id_seq OWNED BY public.mobile_money_accounts.id;


--
-- Name: mobile_money_transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mobile_money_transactions (
    id bigint NOT NULL,
    user_id integer NOT NULL,
    type character varying(50) NOT NULL,
    amount numeric(20,2) NOT NULL,
    fee numeric(10,2) DEFAULT 0.00,
    reference character varying(255),
    recipient_phone character varying(50),
    network character varying(20),
    status character varying(20) DEFAULT 'pending'::character varying,
    wallet_type character varying(20),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone
);


ALTER TABLE public.mobile_money_transactions OWNER TO postgres;

--
-- Name: mobile_money_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mobile_money_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mobile_money_transactions_id_seq OWNER TO postgres;

--
-- Name: mobile_money_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mobile_money_transactions_id_seq OWNED BY public.mobile_money_transactions.id;


--
-- Name: mobile_money_transfers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mobile_money_transfers (
    id bigint NOT NULL,
    user_id integer NOT NULL,
    recipient_phone character varying(20) NOT NULL,
    amount numeric NOT NULL,
    fee numeric DEFAULT 0,
    network character varying(20),
    status character varying(20) DEFAULT 'pending'::character varying,
    reference character varying(100),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone
);


ALTER TABLE public.mobile_money_transfers OWNER TO postgres;

--
-- Name: mobile_money_transfers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mobile_money_transfers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mobile_money_transfers_id_seq OWNER TO postgres;

--
-- Name: mobile_money_transfers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mobile_money_transfers_id_seq OWNED BY public.mobile_money_transfers.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    id integer NOT NULL,
    user_id integer NOT NULL,
    token character varying(255) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    last_activity timestamp without time zone
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- Name: sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sessions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sessions_id_seq OWNER TO postgres;

--
-- Name: sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sessions_id_seq OWNED BY public.sessions.id;


--
-- Name: settings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.settings (
    key character varying(255) NOT NULL,
    value text
);


ALTER TABLE public.settings OWNER TO postgres;

--
-- Name: settlement_positions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.settlement_positions (
    id bigint NOT NULL,
    counterparty character varying(100),
    net_amount numeric(20,2),
    cycle_date date,
    status character varying(20)
);


ALTER TABLE public.settlement_positions OWNER TO postgres;

--
-- Name: settlement_positions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.settlement_positions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.settlement_positions_id_seq OWNER TO postgres;

--
-- Name: settlement_positions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.settlement_positions_id_seq OWNED BY public.settlement_positions.id;


--
-- Name: settlement_transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.settlement_transactions (
    id bigint NOT NULL,
    position_id bigint,
    bank_reference character varying(100),
    settled_amount numeric(20,2),
    settled_at timestamp without time zone
);


ALTER TABLE public.settlement_transactions OWNER TO postgres;

--
-- Name: settlement_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.settlement_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.settlement_transactions_id_seq OWNER TO postgres;

--
-- Name: settlement_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.settlement_transactions_id_seq OWNED BY public.settlement_transactions.id;


--
-- Name: sms; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sms (
    id integer NOT NULL,
    user_id integer,
    target_number character varying(255) NOT NULL,
    message text NOT NULL,
    cost numeric(15,2) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    sender_number character varying(255),
    direction character varying(50) NOT NULL
);


ALTER TABLE public.sms OWNER TO postgres;

--
-- Name: sms_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sms_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sms_id_seq OWNER TO postgres;

--
-- Name: sms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sms_id_seq OWNED BY public.sms.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.subscriptions (
    id integer NOT NULL,
    user_id integer NOT NULL,
    bundle_id integer NOT NULL,
    expiry_date date NOT NULL
);


ALTER TABLE public.subscriptions OWNER TO postgres;

--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subscriptions_id_seq OWNER TO postgres;

--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: suspicious_activity_reports; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.suspicious_activity_reports (
    id bigint NOT NULL,
    user_id integer,
    aggregated_amount numeric(20,2),
    monitoring_period_days integer,
    status character varying(20),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.suspicious_activity_reports OWNER TO postgres;

--
-- Name: suspicious_activity_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.suspicious_activity_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.suspicious_activity_reports_id_seq OWNER TO postgres;

--
-- Name: suspicious_activity_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.suspicious_activity_reports_id_seq OWNED BY public.suspicious_activity_reports.id;


--
-- Name: transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.transactions (
    id integer NOT NULL,
    user_id integer NOT NULL,
    type character varying(50) NOT NULL,
    amount numeric(15,2) NOT NULL,
    status character varying(50) DEFAULT 'success'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.transactions OWNER TO postgres;

--
-- Name: transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.transactions_id_seq OWNER TO postgres;

--
-- Name: transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.transactions_id_seq OWNED BY public.transactions.id;


--
-- Name: transfer_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.transfer_tokens (
    id bigint NOT NULL,
    token character varying(255) NOT NULL,
    external_id character varying(255),
    payer_phone character varying(255),
    payee_phone character varying(255),
    amount numeric(15,2),
    currency character(3) DEFAULT 'BWP'::bpchar,
    expires_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    consumed boolean DEFAULT false,
    consumed_at timestamp without time zone,
    consumed_by integer
);


ALTER TABLE public.transfer_tokens OWNER TO postgres;

--
-- Name: transfer_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.transfer_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.transfer_tokens_id_seq OWNER TO postgres;

--
-- Name: transfer_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.transfer_tokens_id_seq OWNED BY public.transfer_tokens.id;


--
-- Name: trust_reconciliation; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.trust_reconciliation (
    id bigint NOT NULL,
    bank_reported_balance numeric(20,2),
    emoney_liability numeric(20,2),
    variance numeric(20,2),
    status character varying(20),
    checked_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.trust_reconciliation OWNER TO postgres;

--
-- Name: trust_reconciliation_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.trust_reconciliation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.trust_reconciliation_id_seq OWNER TO postgres;

--
-- Name: trust_reconciliation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.trust_reconciliation_id_seq OWNED BY public.trust_reconciliation.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    phone_number character varying(255) NOT NULL,
    email character varying(255),
    password_hash character varying(255) NOT NULL,
    pin_hash character(4) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    pin_failed_attempts integer DEFAULT 0,
    pin_locked_until timestamp without time zone
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: vw_sms_history; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.vw_sms_history AS
 SELECT 'inbound'::text AS direction,
    instant_sms_inbox.from_phone AS sender,
    instant_sms_inbox.to_phone AS recipient,
    instant_sms_inbox.message,
    instant_sms_inbox.received_at AS created_at
   FROM public.instant_sms_inbox
UNION ALL
 SELECT 'outbound'::text AS direction,
    instant_sms_outbox.from_phone AS sender,
    instant_sms_outbox.to_phone AS recipient,
    instant_sms_outbox.message,
    instant_sms_outbox.created_at
   FROM public.instant_sms_outbox;


ALTER VIEW public.vw_sms_history OWNER TO postgres;

--
-- Name: wallet_accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.wallet_accounts (
    user_id integer NOT NULL,
    ledger_account_id integer NOT NULL
);


ALTER TABLE public.wallet_accounts OWNER TO postgres;

--
-- Name: wallets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.wallets (
    id integer NOT NULL,
    user_id integer NOT NULL,
    balance numeric(15,2) DEFAULT 0.00,
    last_updated timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    saccus_ewallet_balance numeric(15,2) DEFAULT 0.00 NOT NULL,
    credit_balance numeric(15,2) DEFAULT 0.00 NOT NULL,
    ledger_account_id integer,
    wallet_type character varying(50) DEFAULT 'main'::character varying
);


ALTER TABLE public.wallets OWNER TO postgres;

--
-- Name: wallets_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.wallets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wallets_id_seq OWNER TO postgres;

--
-- Name: wallets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.wallets_id_seq OWNED BY public.wallets.id;


--
-- Name: agent_float_accounts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agent_float_accounts ALTER COLUMN id SET DEFAULT nextval('public.agent_float_accounts_id_seq'::regclass);


--
-- Name: agent_float_movements id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agent_float_movements ALTER COLUMN id SET DEFAULT nextval('public.agent_float_movements_id_seq'::regclass);


--
-- Name: agents id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agents ALTER COLUMN id SET DEFAULT nextval('public.agents_id_seq'::regclass);


--
-- Name: airtime_purchases id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.airtime_purchases ALTER COLUMN id SET DEFAULT nextval('public.airtime_purchases_id_seq'::regclass);


--
-- Name: aml_flags id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.aml_flags ALTER COLUMN id SET DEFAULT nextval('public.aml_flags_id_seq'::regclass);


--
-- Name: api_endpoints id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.api_endpoints ALTER COLUMN id SET DEFAULT nextval('public.api_endpoints_id_seq'::regclass);


--
-- Name: api_keys id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.api_keys ALTER COLUMN id SET DEFAULT nextval('public.api_keys_id_seq'::regclass);


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: bundles id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bundles ALTER COLUMN id SET DEFAULT nextval('public.bundles_id_seq'::regclass);


--
-- Name: calls id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calls ALTER COLUMN id SET DEFAULT nextval('public.calls_id_seq'::regclass);


--
-- Name: cazacom_middleman id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cazacom_middleman ALTER COLUMN id SET DEFAULT nextval('public.cazacom_middleman_id_seq'::regclass);


--
-- Name: cross_wallet_transfers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cross_wallet_transfers ALTER COLUMN id SET DEFAULT nextval('public.cross_wallet_transfers_id_seq'::regclass);


--
-- Name: data_subscriptions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.data_subscriptions_id_seq'::regclass);


--
-- Name: instant_money id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money ALTER COLUMN id SET DEFAULT nextval('public.instant_money_id_seq'::regclass);


--
-- Name: instant_money_transactions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money_transactions ALTER COLUMN id SET DEFAULT nextval('public.instant_money_transactions_id_seq'::regclass);


--
-- Name: instant_sms_inbox id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_sms_inbox ALTER COLUMN id SET DEFAULT nextval('public.instant_sms_inbox_id_seq'::regclass);


--
-- Name: instant_sms_outbox id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_sms_outbox ALTER COLUMN id SET DEFAULT nextval('public.instant_sms_outbox_id_seq'::regclass);


--
-- Name: instant_transfers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_transfers ALTER COLUMN id SET DEFAULT nextval('public.instant_transfers_id_seq'::regclass);


--
-- Name: ledger_accounts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_accounts ALTER COLUMN id SET DEFAULT nextval('public.ledger_accounts_id_seq'::regclass);


--
-- Name: ledger_entries id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.ledger_entries_id_seq'::regclass);


--
-- Name: mno_ledger id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mno_ledger ALTER COLUMN id SET DEFAULT nextval('public.mno_ledger_id_seq'::regclass);


--
-- Name: mno_trust_accounts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mno_trust_accounts ALTER COLUMN id SET DEFAULT nextval('public.mno_trust_accounts_id_seq'::regclass);


--
-- Name: mobile_money_accounts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_accounts ALTER COLUMN id SET DEFAULT nextval('public.mobile_money_accounts_id_seq'::regclass);


--
-- Name: mobile_money_transactions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_transactions ALTER COLUMN id SET DEFAULT nextval('public.mobile_money_transactions_id_seq'::regclass);


--
-- Name: mobile_money_transfers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_transfers ALTER COLUMN id SET DEFAULT nextval('public.mobile_money_transfers_id_seq'::regclass);


--
-- Name: sessions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions ALTER COLUMN id SET DEFAULT nextval('public.sessions_id_seq'::regclass);


--
-- Name: settlement_positions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settlement_positions ALTER COLUMN id SET DEFAULT nextval('public.settlement_positions_id_seq'::regclass);


--
-- Name: settlement_transactions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settlement_transactions ALTER COLUMN id SET DEFAULT nextval('public.settlement_transactions_id_seq'::regclass);


--
-- Name: sms id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms ALTER COLUMN id SET DEFAULT nextval('public.sms_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: suspicious_activity_reports id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.suspicious_activity_reports ALTER COLUMN id SET DEFAULT nextval('public.suspicious_activity_reports_id_seq'::regclass);


--
-- Name: transactions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transactions ALTER COLUMN id SET DEFAULT nextval('public.transactions_id_seq'::regclass);


--
-- Name: transfer_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transfer_tokens ALTER COLUMN id SET DEFAULT nextval('public.transfer_tokens_id_seq'::regclass);


--
-- Name: trust_reconciliation id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trust_reconciliation ALTER COLUMN id SET DEFAULT nextval('public.trust_reconciliation_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: wallets id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets ALTER COLUMN id SET DEFAULT nextval('public.wallets_id_seq'::regclass);


--
-- Data for Name: agent_float_accounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.agent_float_accounts (id, agent_id, ledger_account_id, float_limit, created_at) FROM stdin;
1	1	101	5000.00	2026-02-12 18:51:51.401804
\.


--
-- Data for Name: agent_float_movements; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.agent_float_movements (id, agent_id, amount, direction, method, created_at) FROM stdin;
\.


--
-- Data for Name: agents; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.agents (id, business_name, phone_number, kyc_status, status, created_at) FROM stdin;
1	SmartShop	+26771234567	pending	active	2026-02-12 18:44:11.107007
\.


--
-- Data for Name: airtime_purchases; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.airtime_purchases (id, user_id, phone_number, amount, network, status, reference, created_at, completed_at) FROM stdin;
\.


--
-- Data for Name: aml_flags; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.aml_flags (id, user_id, transaction_ref, risk_level, reason, reported, created_at) FROM stdin;
\.


--
-- Data for Name: api_endpoints; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.api_endpoints (id, name, endpoint, method, description, is_active, created_at) FROM stdin;
\.


--
-- Data for Name: api_keys; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.api_keys (id, client_name, api_key, permissions, status, created_at, last_used_at) FROM stdin;
1	Test User 1 API	a78469d4-849d-4a12-b0bf-2656d46c71e8	full	active	2025-12-12 13:08:01.095656	\N
2	Test User 2 API	91e99b4f-74a7-4e9d-9c86-e938ca35e2fc	full	active	2025-12-12 13:08:01.095656	\N
\.


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.audit_logs (id, actor_type, actor_id, action, entity, record_id, old_data, new_data, created_at) FROM stdin;
\.


--
-- Data for Name: bundles; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.bundles (id, name, data_mb, price, validity_days) FROM stdin;
\.


--
-- Data for Name: calls; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.calls (id, user_id, target_number, duration, cost, created_at) FROM stdin;
\.


--
-- Data for Name: cazacom_middleman; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cazacom_middleman (id, phone_number, api_key, created_at) FROM stdin;
\.


--
-- Data for Name: cross_wallet_transfers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cross_wallet_transfers (id, user_id, source_wallet, destination_wallet, amount, fee, reference, status, created_at, completed_at) FROM stdin;
\.


--
-- Data for Name: data_subscriptions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.data_subscriptions (id, user_id, bundle_name, activated_at, expires_at) FROM stdin;
\.


--
-- Data for Name: instant_money; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.instant_money (id, sender_id, sender_phone, recipient_phone, amount, token, status, created_at, redeemed_at, trust_account_id) FROM stdin;
\.


--
-- Data for Name: instant_money_transactions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.instant_money_transactions (id, sender_id, recipient_phone, voucher_code, pin_code, amount, status, channel, created_at, redeemed_at) FROM stdin;
\.


--
-- Data for Name: instant_sms_inbox; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.instant_sms_inbox (id, provider, from_phone, to_phone, message, received_at, parsed_at, parse_result, processed, raw_payload) FROM stdin;
\.


--
-- Data for Name: instant_sms_outbox; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.instant_sms_outbox (id, provider, to_phone, from_phone, message, status, attempts, last_attempt_at, created_at, last_error, related_transfer_id) FROM stdin;
\.


--
-- Data for Name: instant_transfers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.instant_transfers (id, external_id, token, source_bank_code, payer_phone, payee_phone, amount, fee, commission, currency, status, created_at, processed_at, raw_payload, processed_by, reversal_of, notes) FROM stdin;
\.


--
-- Data for Name: ledger_accounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ledger_accounts (id, account_code, account_name, account_type, owner_type, owner_id, currency, created_at) FROM stdin;
1	MM-3	Mobile Money Account of Test User 1	LIABILITY	USER	3	BWP	2026-02-12 21:00:37.752689
2	MM-4	Mobile Money Account of Test User 2	LIABILITY	USER	4	BWP	2026-02-12 21:00:37.752689
\.


--
-- Data for Name: ledger_entries; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ledger_entries (id, debit_account, credit_account, amount, reference_type, reference_id, narration, created_at) FROM stdin;
\.


--
-- Data for Name: mno_ledger; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.mno_ledger (id, reference, debit_account, credit_account, amount, currency, created_at) FROM stdin;
\.


--
-- Data for Name: mno_trust_accounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.mno_trust_accounts (id, bank_name, account_number, currency, balance, last_reconciled_at) FROM stdin;
\.


--
-- Data for Name: mobile_money_account_ledgers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.mobile_money_account_ledgers (user_id, ledger_account_id) FROM stdin;
3	1
4	2
\.


--
-- Data for Name: mobile_money_accounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.mobile_money_accounts (id, user_id, balance, credit_balance, last_updated) FROM stdin;
1	3	1000.00	1000.00	2026-02-12 21:01:41.943679
2	4	1000.00	1000.00	2026-02-12 21:01:41.943679
\.


--
-- Data for Name: mobile_money_transactions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.mobile_money_transactions (id, user_id, type, amount, fee, reference, recipient_phone, network, status, wallet_type, created_at, completed_at) FROM stdin;
\.


--
-- Data for Name: mobile_money_transfers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.mobile_money_transfers (id, user_id, recipient_phone, amount, fee, network, status, reference, created_at, completed_at) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sessions (id, user_id, token, expires_at, last_activity) FROM stdin;
12	3	971582dac07a751e487433be2626f7859e0f607fe1aef7a5cf706819c5fd8dd3	2026-03-09 18:06:40	2026-03-02 18:06:40
\.


--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.settings (key, value) FROM stdin;
\.


--
-- Data for Name: settlement_positions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.settlement_positions (id, counterparty, net_amount, cycle_date, status) FROM stdin;
\.


--
-- Data for Name: settlement_transactions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.settlement_transactions (id, position_id, bank_reference, settled_amount, settled_at) FROM stdin;
\.


--
-- Data for Name: sms; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sms (id, user_id, target_number, message, cost, created_at, sender_number, direction) FROM stdin;
7	3	+26770000000	You received P100 via eWallet. PIN: 959530	0.00	2025-12-29 16:13:36.007734	+26770000000	in
8	3	+26770000000	You sent P100 from account CUR00000002 to +26770000000	0.00	2025-12-29 16:13:36.007734	+26770000000	out
9	3	+26770000000	You received P100 via eWallet. PIN: 907310	0.00	2025-12-29 16:13:36.008427	+26770000000	in
10	3	+26770000000	You sent P100 from account CUR00000002 to +26770000000	0.00	2025-12-29 16:13:36.008427	+26770000000	out
13	3	+26770000000	Your SWAP registration OTP is: 316756.	0.00	2025-12-30 12:43:26.89549	SWAP_AUTH	out
14	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 19	0.00	2025-12-30 17:48:13.6162	SYSTEM	sent
15	3	+26770000000	Cashout successful. BWP 100.00. Ref: 19	0.00	2025-12-30 17:48:13.621036	SYSTEM	received
16	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 20	0.00	2025-12-30 17:51:00.335657	SYSTEM	sent
17	3	+26770000000	Cashout successful. BWP 100.00. Ref: 20	0.00	2025-12-30 17:51:00.339253	SYSTEM	received
18	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 21	0.00	2025-12-30 17:58:05.858502	SYSTEM	sent
19	3	+26770000000	Cashout successful. BWP 100.00. Ref: 21	0.00	2025-12-30 17:58:05.86104	SYSTEM	received
20	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 22	0.00	2025-12-30 18:00:35.528374	SYSTEM	sent
21	3	+26770000000	Cashout successful. BWP 100.00. Ref: 22	0.00	2025-12-30 18:00:35.530594	SYSTEM	received
22	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 23	0.00	2025-12-30 20:15:36.507168	SYSTEM	sent
23	3	+26770000000	Cashout successful. BWP 100.00. Ref: 23	0.00	2025-12-30 20:15:36.510771	SYSTEM	received
24	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 24	0.00	2025-12-30 20:23:33.299373	SYSTEM	sent
25	3	+26770000000	Cashout successful. BWP 100.00. Ref: 24	0.00	2025-12-30 20:23:33.30214	SYSTEM	received
30	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 28	0.00	2026-01-03 02:11:54.352079	SYSTEM	sent
31	3	+26770000000	Cashout successful. BWP 100.00. Ref: 28	0.00	2026-01-03 02:11:54.37022	SYSTEM	received
33	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 29	0.00	2026-01-03 02:29:26.557134	SYSTEM	sent
34	3	+26770000000	Cashout successful. BWP 100.00. Ref: 29	0.00	2026-01-03 02:29:26.562159	SYSTEM	received
36	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 30	0.00	2026-01-03 09:29:46.770895	SYSTEM	sent
37	3	+26770000000	Cashout successful. BWP 100.00. Ref: 30	0.00	2026-01-03 09:29:46.780095	SYSTEM	received
39	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 31	0.00	2026-01-03 09:38:20.941497	SYSTEM	sent
40	3	+26770000000	Cashout successful. BWP 100.00. Ref: 31	0.00	2026-01-03 09:38:20.944485	SYSTEM	received
42	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 32	0.00	2026-01-03 09:43:14.309661	SYSTEM	sent
43	3	+26770000000	Cashout successful. BWP 100.00. Ref: 32	0.00	2026-01-03 09:43:14.313077	SYSTEM	received
45	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 33	0.00	2026-01-03 09:58:54.296982	SYSTEM	sent
46	3	+26770000000	Cashout successful. BWP 100.00. Ref: 33	0.00	2026-01-03 09:58:54.299961	SYSTEM	received
48	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 34	0.00	2026-01-03 10:12:15.01565	SYSTEM	sent
49	3	+26770000000	Cashout successful. BWP 100.00. Ref: 34	0.00	2026-01-03 10:12:15.01781	SYSTEM	received
51	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 35	0.00	2026-01-03 10:12:58.105204	SYSTEM	sent
52	3	+26770000000	Cashout successful. BWP 100.00. Ref: 35	0.00	2026-01-03 10:12:58.111188	SYSTEM	received
54	3	+26770000000	Your SWAP registration OTP is: 593722.	0.00	2026-01-24 21:03:24.231868	SWAP_AUTH	out
55	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 36	0.00	2026-01-25 01:46:10.616016	SYSTEM	sent
56	3	+26770000000	Cashout successful. BWP 100.00. Ref: 36	0.00	2026-01-25 01:46:10.641609	SYSTEM	received
58	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 37	0.00	2026-01-25 02:42:06.106172	SYSTEM	sent
59	3	+26770000000	Cashout successful. BWP 100.00. Ref: 37	0.00	2026-01-25 02:42:06.109499	SYSTEM	received
61	\N	+26770000000	Cashout successful. BWP 100.00. Ref: 38	0.00	2026-01-25 10:50:33.639069	SYSTEM	sent
62	3	+26770000000	Cashout successful. BWP 100.00. Ref: 38	0.00	2026-01-25 10:50:33.643648	SYSTEM	received
69	\N	+26770000000	Cashout successful. BWP 87.80. Ref: 44	0.00	2026-02-09 09:27:04.219397	SYSTEM	sent
70	3	+26770000000	Cashout successful. BWP 87.80. Ref: 44	0.00	2026-02-09 09:27:04.228738	SYSTEM	received
79	\N	26770000000	Test without auth	0.00	2026-03-02 18:05:15.564278	SYSTEM	sent
80	\N	26770000000	Test with internal key - 2026-03-02 17:05:15	0.00	2026-03-02 18:05:15.675778	SYSTEM	sent
81	\N	26770000000	Test without auth	0.00	2026-03-02 18:06:50.684335	SYSTEM	sent
82	\N	26770000000	Test with internal key - 2026-03-02 17:06:50	0.00	2026-03-02 18:06:50.74233	SYSTEM	sent
83	\N	26770000000	Test without auth	0.00	2026-03-02 18:07:25.166055	SYSTEM	sent
84	\N	26770000000	Test with internal key - 2026-03-02 17:07:25	0.00	2026-03-02 18:07:25.195632	SYSTEM	sent
85	\N	26770000000	Test without auth	0.00	2026-03-02 18:07:57.471052	SYSTEM	sent
86	\N	26770000000	Test with internal key - 2026-03-02 17:07:57	0.00	2026-03-02 18:07:57.506106	SYSTEM	sent
87	\N	26770000000	🔐 VouchMorph Withdrawal\nCode: 840983\nAmount: 1500 BWP\nValid for 24 hours.\nKeep this code secure!	0.00	2026-03-02 18:20:19.917902	SYSTEM	sent
88	\N	26770000000	🔐 VouchMorph Withdrawal\nCode: 716775\nAmount: 1500 BWP\nValid for 24 hours.\nKeep this code secure!	0.00	2026-03-02 19:30:31.387568	SYSTEM	sent
\.


--
-- Data for Name: subscriptions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.subscriptions (id, user_id, bundle_id, expiry_date) FROM stdin;
\.


--
-- Data for Name: suspicious_activity_reports; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.suspicious_activity_reports (id, user_id, aggregated_amount, monitoring_period_days, status, created_at) FROM stdin;
\.


--
-- Data for Name: transactions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.transactions (id, user_id, type, amount, status, created_at) FROM stdin;
\.


--
-- Data for Name: transfer_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.transfer_tokens (id, token, external_id, payer_phone, payee_phone, amount, currency, expires_at, created_at, consumed, consumed_at, consumed_by) FROM stdin;
\.


--
-- Data for Name: trust_reconciliation; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.trust_reconciliation (id, bank_reported_balance, emoney_liability, variance, status, checked_at) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, name, phone_number, email, password_hash, pin_hash, created_at, pin_failed_attempts, pin_locked_until) FROM stdin;
3	Test User 1	+26770000000	user1@example.com	password_hash_1	1234	2025-12-12 13:08:01.095656	0	\N
4	Test User 2	+26770000001	user2@example.com	password_hash_2	1234	2025-12-12 13:08:01.095656	0	\N
\.


--
-- Data for Name: wallet_accounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.wallet_accounts (user_id, ledger_account_id) FROM stdin;
\.


--
-- Data for Name: wallets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.wallets (id, user_id, balance, last_updated, saccus_ewallet_balance, credit_balance, ledger_account_id, wallet_type) FROM stdin;
4	4	0.00	2025-12-12 13:08:01.095656	0.00	0.00	\N	main
3	3	0.00	2025-12-12 13:08:01.095656	200.00	0.00	\N	main
\.


--
-- Name: agent_float_accounts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.agent_float_accounts_id_seq', 1, true);


--
-- Name: agent_float_movements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.agent_float_movements_id_seq', 1, false);


--
-- Name: agents_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.agents_id_seq', 1, true);


--
-- Name: airtime_purchases_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.airtime_purchases_id_seq', 1, false);


--
-- Name: aml_flags_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.aml_flags_id_seq', 1, false);


--
-- Name: api_endpoints_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.api_endpoints_id_seq', 1, false);


--
-- Name: api_keys_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.api_keys_id_seq', 2, true);


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 1, false);


--
-- Name: bundles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.bundles_id_seq', 1, false);


--
-- Name: calls_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.calls_id_seq', 1, false);


--
-- Name: cazacom_middleman_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cazacom_middleman_id_seq', 1, false);


--
-- Name: cross_wallet_transfers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cross_wallet_transfers_id_seq', 1, false);


--
-- Name: data_subscriptions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.data_subscriptions_id_seq', 1, false);


--
-- Name: instant_money_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.instant_money_id_seq', 1, false);


--
-- Name: instant_money_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.instant_money_transactions_id_seq', 1, false);


--
-- Name: instant_sms_inbox_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.instant_sms_inbox_id_seq', 1, false);


--
-- Name: instant_sms_outbox_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.instant_sms_outbox_id_seq', 1, false);


--
-- Name: instant_transfers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.instant_transfers_id_seq', 1, false);


--
-- Name: ledger_accounts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ledger_accounts_id_seq', 2, true);


--
-- Name: ledger_entries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ledger_entries_id_seq', 1, false);


--
-- Name: mno_ledger_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mno_ledger_id_seq', 1, false);


--
-- Name: mno_trust_accounts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mno_trust_accounts_id_seq', 1, false);


--
-- Name: mobile_money_accounts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mobile_money_accounts_id_seq', 2, true);


--
-- Name: mobile_money_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mobile_money_transactions_id_seq', 1, false);


--
-- Name: mobile_money_transfers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mobile_money_transfers_id_seq', 1, false);


--
-- Name: sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sessions_id_seq', 12, true);


--
-- Name: settlement_positions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.settlement_positions_id_seq', 1, false);


--
-- Name: settlement_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.settlement_transactions_id_seq', 1, false);


--
-- Name: sms_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sms_id_seq', 88, true);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.subscriptions_id_seq', 1, false);


--
-- Name: suspicious_activity_reports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.suspicious_activity_reports_id_seq', 1, false);


--
-- Name: transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.transactions_id_seq', 1, false);


--
-- Name: transfer_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.transfer_tokens_id_seq', 1, false);


--
-- Name: trust_reconciliation_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.trust_reconciliation_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 4, true);


--
-- Name: wallets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.wallets_id_seq', 4, true);


--
-- Name: agent_float_accounts agent_float_accounts_agent_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agent_float_accounts
    ADD CONSTRAINT agent_float_accounts_agent_id_key UNIQUE (agent_id);


--
-- Name: agent_float_accounts agent_float_accounts_ledger_account_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agent_float_accounts
    ADD CONSTRAINT agent_float_accounts_ledger_account_id_key UNIQUE (ledger_account_id);


--
-- Name: agent_float_accounts agent_float_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agent_float_accounts
    ADD CONSTRAINT agent_float_accounts_pkey PRIMARY KEY (id);


--
-- Name: agent_float_movements agent_float_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agent_float_movements
    ADD CONSTRAINT agent_float_movements_pkey PRIMARY KEY (id);


--
-- Name: agents agents_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_pkey PRIMARY KEY (id);


--
-- Name: airtime_purchases airtime_purchases_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.airtime_purchases
    ADD CONSTRAINT airtime_purchases_pkey PRIMARY KEY (id);


--
-- Name: aml_flags aml_flags_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.aml_flags
    ADD CONSTRAINT aml_flags_pkey PRIMARY KEY (id);


--
-- Name: api_endpoints api_endpoints_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.api_endpoints
    ADD CONSTRAINT api_endpoints_pkey PRIMARY KEY (id);


--
-- Name: api_keys api_keys_api_key_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_api_key_key UNIQUE (api_key);


--
-- Name: api_keys api_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: bundles bundles_name_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bundles
    ADD CONSTRAINT bundles_name_key UNIQUE (name);


--
-- Name: bundles bundles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bundles
    ADD CONSTRAINT bundles_pkey PRIMARY KEY (id);


--
-- Name: calls calls_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calls
    ADD CONSTRAINT calls_pkey PRIMARY KEY (id);


--
-- Name: cazacom_middleman cazacom_middleman_phone_number_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cazacom_middleman
    ADD CONSTRAINT cazacom_middleman_phone_number_key UNIQUE (phone_number);


--
-- Name: cazacom_middleman cazacom_middleman_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cazacom_middleman
    ADD CONSTRAINT cazacom_middleman_pkey PRIMARY KEY (id);


--
-- Name: cross_wallet_transfers cross_wallet_transfers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cross_wallet_transfers
    ADD CONSTRAINT cross_wallet_transfers_pkey PRIMARY KEY (id);


--
-- Name: cross_wallet_transfers cross_wallet_transfers_reference_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cross_wallet_transfers
    ADD CONSTRAINT cross_wallet_transfers_reference_key UNIQUE (reference);


--
-- Name: data_subscriptions data_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_subscriptions
    ADD CONSTRAINT data_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: instant_money instant_money_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money
    ADD CONSTRAINT instant_money_pkey PRIMARY KEY (id);


--
-- Name: instant_money instant_money_token_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money
    ADD CONSTRAINT instant_money_token_key UNIQUE (token);


--
-- Name: instant_money_transactions instant_money_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money_transactions
    ADD CONSTRAINT instant_money_transactions_pkey PRIMARY KEY (id);


--
-- Name: instant_sms_inbox instant_sms_inbox_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_sms_inbox
    ADD CONSTRAINT instant_sms_inbox_pkey PRIMARY KEY (id);


--
-- Name: instant_sms_outbox instant_sms_outbox_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_sms_outbox
    ADD CONSTRAINT instant_sms_outbox_pkey PRIMARY KEY (id);


--
-- Name: instant_transfers instant_transfers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_transfers
    ADD CONSTRAINT instant_transfers_pkey PRIMARY KEY (id);


--
-- Name: ledger_accounts ledger_accounts_account_code_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_accounts
    ADD CONSTRAINT ledger_accounts_account_code_key UNIQUE (account_code);


--
-- Name: ledger_accounts ledger_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_accounts
    ADD CONSTRAINT ledger_accounts_pkey PRIMARY KEY (id);


--
-- Name: ledger_entries ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: mno_ledger mno_ledger_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mno_ledger
    ADD CONSTRAINT mno_ledger_pkey PRIMARY KEY (id);


--
-- Name: mno_trust_accounts mno_trust_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mno_trust_accounts
    ADD CONSTRAINT mno_trust_accounts_pkey PRIMARY KEY (id);


--
-- Name: mobile_money_account_ledgers mobile_money_account_ledgers_ledger_account_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_account_ledgers
    ADD CONSTRAINT mobile_money_account_ledgers_ledger_account_id_key UNIQUE (ledger_account_id);


--
-- Name: mobile_money_account_ledgers mobile_money_account_ledgers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_account_ledgers
    ADD CONSTRAINT mobile_money_account_ledgers_pkey PRIMARY KEY (user_id);


--
-- Name: mobile_money_accounts mobile_money_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_accounts
    ADD CONSTRAINT mobile_money_accounts_pkey PRIMARY KEY (id);


--
-- Name: mobile_money_accounts mobile_money_accounts_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_accounts
    ADD CONSTRAINT mobile_money_accounts_user_id_key UNIQUE (user_id);


--
-- Name: mobile_money_transactions mobile_money_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_transactions
    ADD CONSTRAINT mobile_money_transactions_pkey PRIMARY KEY (id);


--
-- Name: mobile_money_transactions mobile_money_transactions_reference_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_transactions
    ADD CONSTRAINT mobile_money_transactions_reference_key UNIQUE (reference);


--
-- Name: mobile_money_transfers mobile_money_transfers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_transfers
    ADD CONSTRAINT mobile_money_transfers_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_token_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_token_key UNIQUE (token);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (key);


--
-- Name: settlement_positions settlement_positions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settlement_positions
    ADD CONSTRAINT settlement_positions_pkey PRIMARY KEY (id);


--
-- Name: settlement_transactions settlement_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settlement_transactions
    ADD CONSTRAINT settlement_transactions_pkey PRIMARY KEY (id);


--
-- Name: sms sms_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms
    ADD CONSTRAINT sms_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: suspicious_activity_reports suspicious_activity_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.suspicious_activity_reports
    ADD CONSTRAINT suspicious_activity_reports_pkey PRIMARY KEY (id);


--
-- Name: transactions transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_pkey PRIMARY KEY (id);


--
-- Name: transfer_tokens transfer_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transfer_tokens
    ADD CONSTRAINT transfer_tokens_pkey PRIMARY KEY (id);


--
-- Name: transfer_tokens transfer_tokens_token_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transfer_tokens
    ADD CONSTRAINT transfer_tokens_token_key UNIQUE (token);


--
-- Name: trust_reconciliation trust_reconciliation_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trust_reconciliation
    ADD CONSTRAINT trust_reconciliation_pkey PRIMARY KEY (id);


--
-- Name: users users_phone_number_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_phone_number_key UNIQUE (phone_number);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: wallet_accounts wallet_accounts_ledger_account_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallet_accounts
    ADD CONSTRAINT wallet_accounts_ledger_account_id_key UNIQUE (ledger_account_id);


--
-- Name: wallet_accounts wallet_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallet_accounts
    ADD CONSTRAINT wallet_accounts_pkey PRIMARY KEY (user_id);


--
-- Name: wallets wallets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_pkey PRIMARY KEY (id);


--
-- Name: wallets wallets_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_user_id_key UNIQUE (user_id);


--
-- Name: idx_agent_float_accounts_agent_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_agent_float_accounts_agent_id ON public.agent_float_accounts USING btree (agent_id);


--
-- Name: idx_agent_float_accounts_ledger_account_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_agent_float_accounts_ledger_account_id ON public.agent_float_accounts USING btree (ledger_account_id);


--
-- Name: idx_agent_float_movements_agent_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_agent_float_movements_agent_id ON public.agent_float_movements USING btree (agent_id);


--
-- Name: idx_cross_wallet_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cross_wallet_user_id ON public.cross_wallet_transfers USING btree (user_id);


--
-- Name: idx_instant_sms_inbox_from_phone; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_instant_sms_inbox_from_phone ON public.instant_sms_inbox USING btree (from_phone);


--
-- Name: idx_instant_sms_outbox_to_phone; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_instant_sms_outbox_to_phone ON public.instant_sms_outbox USING btree (to_phone);


--
-- Name: idx_mm_transactions_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_mm_transactions_status ON public.mobile_money_transactions USING btree (status);


--
-- Name: idx_mm_transactions_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_mm_transactions_user_id ON public.mobile_money_transactions USING btree (user_id);


--
-- Name: idx_mobile_money_accounts_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_mobile_money_accounts_user_id ON public.mobile_money_accounts USING btree (user_id);


--
-- Name: idx_mobile_money_transactions_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_mobile_money_transactions_user_id ON public.mobile_money_transactions USING btree (user_id);


--
-- Name: airtime_purchases airtime_purchases_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.airtime_purchases
    ADD CONSTRAINT airtime_purchases_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: cross_wallet_transfers cross_wallet_transfers_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cross_wallet_transfers
    ADD CONSTRAINT cross_wallet_transfers_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: calls fk_calls_user_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calls
    ADD CONSTRAINT fk_calls_user_id FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: data_subscriptions fk_data_subs_user_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_subscriptions
    ADD CONSTRAINT fk_data_subs_user_id FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: instant_money fk_im_sender_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money
    ADD CONSTRAINT fk_im_sender_id FOREIGN KEY (sender_id) REFERENCES public.users(id);


--
-- Name: instant_money_transactions fk_imt_sender_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money_transactions
    ADD CONSTRAINT fk_imt_sender_id FOREIGN KEY (sender_id) REFERENCES public.users(id);


--
-- Name: instant_sms_outbox fk_iso_related_transfer_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_sms_outbox
    ADD CONSTRAINT fk_iso_related_transfer_id FOREIGN KEY (related_transfer_id) REFERENCES public.instant_transfers(id);


--
-- Name: mobile_money_account_ledgers fk_mm_ledger_account; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_account_ledgers
    ADD CONSTRAINT fk_mm_ledger_account FOREIGN KEY (ledger_account_id) REFERENCES public.ledger_accounts(id);


--
-- Name: mobile_money_account_ledgers fk_mm_ledger_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_account_ledgers
    ADD CONSTRAINT fk_mm_ledger_user FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: sessions fk_sessions_user_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT fk_sessions_user_id FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: sms fk_sms_user_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms
    ADD CONSTRAINT fk_sms_user_id FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: subscriptions fk_subs_bundle_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT fk_subs_bundle_id FOREIGN KEY (bundle_id) REFERENCES public.bundles(id);


--
-- Name: subscriptions fk_subs_user_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT fk_subs_user_id FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: transactions fk_transactions_user_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT fk_transactions_user_id FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: instant_money fk_trust_account; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instant_money
    ADD CONSTRAINT fk_trust_account FOREIGN KEY (trust_account_id) REFERENCES public.mno_trust_accounts(id);


--
-- Name: transfer_tokens fk_tt_consumed_by; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transfer_tokens
    ADD CONSTRAINT fk_tt_consumed_by FOREIGN KEY (consumed_by) REFERENCES public.users(id);


--
-- Name: wallets fk_wallets_user_id; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT fk_wallets_user_id FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: mobile_money_transactions mobile_money_transactions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_transactions
    ADD CONSTRAINT mobile_money_transactions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: mobile_money_transfers mobile_money_transfers_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mobile_money_transfers
    ADD CONSTRAINT mobile_money_transfers_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: wallets wallets_ledger_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_ledger_account_id_fkey FOREIGN KEY (ledger_account_id) REFERENCES public.ledger_accounts(id);


--
-- PostgreSQL database dump complete
--

\unrestrict 23UlbChM9mJPyXcjgaNRfJKjZHRQYScZlkqEvY4kg8E0veea86fsgIOkKhDUj4W

