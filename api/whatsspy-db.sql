--
-- whatsspyQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
--
-- Name: lastseen_privacy_update(); Type: FUNCTION; Schema: public; Owner: whatsspy
--

CREATE FUNCTION lastseen_privacy_update() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

IF (NEW)."lastseen_privacy" != (OLD)."lastseen_privacy" THEN
    INSERT INTO lastseen_privacy_history ("number", "changed_at", "privacy") 
        VALUES((NEW).id, NOW(), (NEW)."lastseen_privacy");
END IF;

RETURN NULL;

END;
$$;


ALTER FUNCTION public.lastseen_privacy_update() OWNER TO whatsspy;

--
-- Name: profilepic_privacy_update(); Type: FUNCTION; Schema: public; Owner: whatsspy
--

CREATE FUNCTION profilepic_privacy_update() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

IF (NEW)."profilepic_privacy" != (OLD)."profilepic_privacy" THEN
    INSERT INTO profilepic_privacy_history ("number", "changed_at", "privacy") 
        VALUES((NEW).id, NOW(), (NEW)."profilepic_privacy");
END IF;

RETURN NULL;

END;
$$;


ALTER FUNCTION public.profilepic_privacy_update() OWNER TO whatsspy;

--
-- Name: statusmessage_privacy_update(); Type: FUNCTION; Schema: public; Owner: whatsspy
--

CREATE FUNCTION statusmessage_privacy_update() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

IF (NEW)."statusmessage_privacy" != (OLD)."statusmessage_privacy" THEN
    INSERT INTO statusmessage_privacy_history ("number", "changed_at", "privacy") 
        VALUES((NEW).id, NOW(), (NEW)."statusmessage_privacy");
END IF;

RETURN NULL;

END;
$$;


ALTER FUNCTION public.statusmessage_privacy_update() OWNER TO whatsspy;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: accounts; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE accounts (
    id character varying(50) NOT NULL,
    active boolean DEFAULT true NOT NULL,
    name character varying(255),
    lastseen_privacy boolean DEFAULT false NOT NULL,
    verified boolean DEFAULT false NOT NULL,
    statusmessage_privacy boolean DEFAULT false NOT NULL,
    profilepic_privacy boolean DEFAULT false NOT NULL,
    notify_status boolean DEFAULT false NOT NULL,
    notify_statusmsg boolean DEFAULT false NOT NULL,
    notify_profilepic boolean DEFAULT false NOT NULL,
    notify_actions boolean DEFAULT false NOT NULL,
    notify_timeline boolean DEFAULT false NOT NULL,
    notify_privacy boolean DEFAULT false NOT NULL,
    read_only_token character varying(255)
);


ALTER TABLE public.accounts OWNER TO whatsspy;

--
-- Name: accounts_to_groups; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE accounts_to_groups (
    number character(50) NOT NULL,
    gid integer NOT NULL
);


ALTER TABLE public.accounts_to_groups OWNER TO whatsspy;

--
-- Name: groups; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE groups (
    gid integer NOT NULL,
    name character varying(255),
    read_only_token character varying(255)
);


ALTER TABLE public.groups OWNER TO whatsspy;

--
-- Name: groups_gid_seq; Type: SEQUENCE; Schema: public; Owner: whatsspy
--

CREATE SEQUENCE groups_gid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.groups_gid_seq OWNER TO whatsspy;

--
-- Name: groups_gid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: whatsspy
--

ALTER SEQUENCE groups_gid_seq OWNED BY groups.gid;


--
-- Name: lastseen_privacy_history; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE lastseen_privacy_history (
    number character varying(50) NOT NULL,
    changed_at timestamp with time zone NOT NULL,
    privacy boolean NOT NULL
);


ALTER TABLE public.lastseen_privacy_history OWNER TO whatsspy;

--
-- Name: profilepic_privacy_history; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE profilepic_privacy_history (
    number character varying(50) NOT NULL,
    changed_at timestamp with time zone NOT NULL,
    privacy boolean NOT NULL
);


ALTER TABLE public.profilepic_privacy_history OWNER TO whatsspy;

--
-- Name: profilepicture_history; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE profilepicture_history (
    number character varying(50) NOT NULL,
    hash character(64) NOT NULL,
    changed_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.profilepicture_history OWNER TO whatsspy;

--
-- Name: status_history; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE status_history (
    status boolean NOT NULL,
    start timestamp with time zone DEFAULT now() NOT NULL,
    "end" timestamp with time zone,
    number character varying(50) NOT NULL,
    sid bigint NOT NULL
);


ALTER TABLE public.status_history OWNER TO whatsspy;

--
-- Name: status_sid_seq; Type: SEQUENCE; Schema: public; Owner: whatsspy
--

CREATE SEQUENCE status_sid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.status_sid_seq OWNER TO whatsspy;

--
-- Name: status_sid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: whatsspy
--

ALTER SEQUENCE status_sid_seq OWNED BY status_history.sid;


--
-- Name: statusmessage_history; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE statusmessage_history (
    number character varying(50) NOT NULL,
    status character varying(255) NOT NULL,
    changed_at timestamp with time zone NOT NULL
);


ALTER TABLE public.statusmessage_history OWNER TO whatsspy;

--
-- Name: statusmessage_privacy_history; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE statusmessage_privacy_history (
    number character varying(50) NOT NULL,
    changed_at timestamp with time zone NOT NULL,
    privacy boolean NOT NULL
);


ALTER TABLE public.statusmessage_privacy_history OWNER TO whatsspy;

--
-- Name: tracker_history; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE tracker_history (
    start timestamp with time zone DEFAULT now() NOT NULL,
    "end" timestamp with time zone,
    reason character varying(255)
);


ALTER TABLE public.tracker_history OWNER TO whatsspy;

--
-- Name: whatsspy_config; Type: TABLE; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE TABLE whatsspy_config (
    db_version integer,
    last_login_attempt timestamp with time zone,
    account_show_timeline_length integer NOT NULL DEFAULT 14,
    account_show_timeline_tracker boolean NOT NULL DEFAULT true
);

INSERT INTO whatsspy_config (db_version)
    VALUES (8);


ALTER TABLE public.whatsspy_config OWNER TO whatsspy;

--
-- Name: gid; Type: DEFAULT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY groups ALTER COLUMN gid SET DEFAULT nextval('groups_gid_seq'::regclass);


--
-- Name: sid; Type: DEFAULT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY status_history ALTER COLUMN sid SET DEFAULT nextval('status_sid_seq'::regclass);


--
-- Name: pk_account_to_group; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY accounts_to_groups
    ADD CONSTRAINT pk_account_to_group PRIMARY KEY (number, gid);


--
-- Name: pk_groups_gid; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT pk_groups_gid PRIMARY KEY (gid);


--
-- Name: pk_phone_id; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY accounts
    ADD CONSTRAINT pk_phone_id PRIMARY KEY (id);


--
-- Name: pk_profilepic_privacy; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY profilepic_privacy_history
    ADD CONSTRAINT pk_profilepic_privacy PRIMARY KEY (number, changed_at);


--
-- Name: pk_sid; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY status_history
    ADD CONSTRAINT pk_sid PRIMARY KEY (sid);


--
-- Name: pk_statusmessage_history; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY statusmessage_history
    ADD CONSTRAINT pk_statusmessage_history PRIMARY KEY (number, changed_at);


--
-- Name: pk_statusmessage_privacy; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY statusmessage_privacy_history
    ADD CONSTRAINT pk_statusmessage_privacy PRIMARY KEY (number, changed_at);


--
-- Name: pk_tracker_history; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY tracker_history
    ADD CONSTRAINT pk_tracker_history PRIMARY KEY (start);


--
-- Name: privacy_history_pkey; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY lastseen_privacy_history
    ADD CONSTRAINT privacy_history_pkey PRIMARY KEY (number, changed_at);


--
-- Name: profilepicture_history_pkey; Type: CONSTRAINT; Schema: public; Owner: whatsspy; Tablespace: 
--

ALTER TABLE ONLY profilepicture_history
    ADD CONSTRAINT profilepicture_history_pkey PRIMARY KEY (number, changed_at);


--
-- Name: index_account_active_verified; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_account_active_verified ON accounts USING btree (active, verified);


--
-- Name: index_account_name; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_account_name ON accounts USING btree (name);


--
-- Name: index_account_notify_profilepic; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_account_notify_profilepic ON accounts USING btree (notify_profilepic);


--
-- Name: index_account_notify_status; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_account_notify_status ON accounts USING btree (notify_status);


--
-- Name: index_account_notify_statusmsg; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_account_notify_statusmsg ON accounts USING btree (notify_statusmsg);


--
-- Name: index_accounts_active_true_verified_true; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_accounts_active_true_verified_true ON accounts USING btree (id) WHERE ((active = true) AND (verified = true));


--
-- Name: index_accounts_to_groups_number_gid; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_accounts_to_groups_number_gid ON accounts_to_groups USING btree (number, gid);


--
-- Name: index_end_status; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_end_status ON status_history USING btree ("end");


--
-- Name: index_group_gid; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_group_gid ON groups USING btree (gid);


--
-- Name: index_lastseen_number_changed_at; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_lastseen_number_changed_at ON lastseen_privacy_history USING btree (number, changed_at DESC NULLS LAST);


--
-- Name: index_lastseen_privacy_history_number_changed_at_desc; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_lastseen_privacy_history_number_changed_at_desc ON lastseen_privacy_history USING btree (number, changed_at DESC);


--
-- Name: index_number_status; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_number_status ON status_history USING btree (number);


--
-- Name: index_profilepic_number_changed_at; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_profilepic_number_changed_at ON profilepic_privacy_history USING btree (number, changed_at DESC NULLS LAST);


--
-- Name: index_profilepic_privacy_history_number_changed_at_desc; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_profilepic_privacy_history_number_changed_at_desc ON profilepic_privacy_history USING btree (number, changed_at DESC);


--
-- Name: index_profilepicture_data_number_changed_at; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_profilepicture_data_number_changed_at ON profilepicture_history USING btree (number, changed_at DESC NULLS LAST);


--
-- Name: index_profilepicture_history_number; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_profilepicture_history_number ON profilepicture_history USING btree (number);


--
-- Name: index_profilepicture_history_number_changed_at_desc; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_profilepicture_history_number_changed_at_desc ON profilepicture_history USING btree (number, changed_at DESC);


--
-- Name: index_start_status; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_start_status ON status_history USING btree (start);


--
-- Name: index_status_history_end_status; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_end_status ON status_history USING btree (status, "end" NULLS FIRST);


--
-- Name: index_status_history_number_end; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_number_end ON status_history USING btree (number, "end" NULLS FIRST);


--
-- Name: index_status_history_number_end_is_null; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_number_end_is_null ON status_history USING btree (number) WHERE ("end" = NULL::timestamp with time zone);


--
-- Name: index_status_history_number_start_asc; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_number_start_asc ON status_history USING btree (number, start);


--
-- Name: index_status_history_number_start_status_true_end_not_null; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_number_start_status_true_end_not_null ON status_history USING btree (number, start DESC) WHERE ((status = true) AND ("end" IS NOT NULL));


--
-- Name: index_status_history_number_status_true_end_not_null; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_number_status_true_end_not_null ON status_history USING btree (number) WHERE ((status = true) AND ("end" IS NOT NULL));


--
-- Name: index_status_history_number_status_true_end_null; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_number_status_true_end_null ON status_history USING btree (number) WHERE ((status = true) AND ("end" IS NULL));


--
-- Name: index_status_history_number_status_true_start_desc; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_number_status_true_start_desc ON status_history USING btree (number, start DESC) WHERE (status = true);


--
-- Name: index_status_history_sid_start_end_status_true; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_sid_start_end_status_true ON status_history USING btree (sid DESC, start DESC, "end" DESC) WHERE (status = true);


--
-- Name: index_status_history_status_true; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_history_status_true ON status_history USING btree (status) WHERE (status = true);


--
-- Name: index_status_status; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_status_status ON status_history USING btree (status);


--
-- Name: index_statusmessage_data_number_changed_at; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_statusmessage_data_number_changed_at ON statusmessage_history USING btree (number, changed_at DESC NULLS LAST);


--
-- Name: index_statusmessage_history_number_changed_at_desc; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_statusmessage_history_number_changed_at_desc ON statusmessage_history USING btree (number, changed_at DESC);


--
-- Name: index_statusmessage_number; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_statusmessage_number ON statusmessage_history USING btree (number);


--
-- Name: index_statusmessage_number_changed_at; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_statusmessage_number_changed_at ON statusmessage_privacy_history USING btree (number, changed_at DESC NULLS LAST);


--
-- Name: index_statusmessage_privacy_history_number_changed_at_desc; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_statusmessage_privacy_history_number_changed_at_desc ON statusmessage_privacy_history USING btree (number, changed_at DESC);


--
-- Name: index_tracker_history_end; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_tracker_history_end ON tracker_history USING btree ("end" NULLS FIRST);


--
-- Name: index_tracker_history_start; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_tracker_history_start ON tracker_history USING btree (start DESC);


--
-- Name: index_tracker_history_start_end_not_null; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX index_tracker_history_start_end_not_null ON tracker_history USING btree (start DESC) WHERE ("end" IS NOT NULL);


--
-- Name: number_status_analytics_count; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX number_status_analytics_count ON status_history USING btree (number);


--
-- Name: number_status_analytics_result; Type: INDEX; Schema: public; Owner: whatsspy; Tablespace: 
--

CREATE INDEX number_status_analytics_result ON status_history USING btree (start, status, number) WHERE (("end" IS NOT NULL) AND (status = true));


--
-- Name: trigger_lastseen; Type: TRIGGER; Schema: public; Owner: whatsspy
--

CREATE TRIGGER trigger_lastseen AFTER UPDATE ON accounts FOR EACH ROW EXECUTE PROCEDURE lastseen_privacy_update();


--
-- Name: trigger_profilepic; Type: TRIGGER; Schema: public; Owner: whatsspy
--

CREATE TRIGGER trigger_profilepic AFTER UPDATE ON accounts FOR EACH ROW EXECUTE PROCEDURE profilepic_privacy_update();


--
-- Name: trigger_statusmessage; Type: TRIGGER; Schema: public; Owner: whatsspy
--

CREATE TRIGGER trigger_statusmessage AFTER UPDATE ON accounts FOR EACH ROW EXECUTE PROCEDURE statusmessage_privacy_update();


--
-- Name: fk_lastseen_privacy_number; Type: FK CONSTRAINT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY lastseen_privacy_history
    ADD CONSTRAINT fk_lastseen_privacy_number FOREIGN KEY (number) REFERENCES accounts(id);


--
-- Name: fk_number_id; Type: FK CONSTRAINT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY status_history
    ADD CONSTRAINT fk_number_id FOREIGN KEY (number) REFERENCES accounts(id);


--
-- Name: fk_profilepic_privacy_number; Type: FK CONSTRAINT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY profilepic_privacy_history
    ADD CONSTRAINT fk_profilepic_privacy_number FOREIGN KEY (number) REFERENCES accounts(id);


--
-- Name: fk_profilepicture_history_number; Type: FK CONSTRAINT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY profilepicture_history
    ADD CONSTRAINT fk_profilepicture_history_number FOREIGN KEY (number) REFERENCES accounts(id);


--
-- Name: fk_statusmessage_history; Type: FK CONSTRAINT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY statusmessage_history
    ADD CONSTRAINT fk_statusmessage_history FOREIGN KEY (number) REFERENCES accounts(id);


--
-- Name: fk_statusmessage_privacy_number; Type: FK CONSTRAINT; Schema: public; Owner: whatsspy
--

ALTER TABLE ONLY statusmessage_privacy_history
    ADD CONSTRAINT fk_statusmessage_privacy_number FOREIGN KEY (number) REFERENCES accounts(id);