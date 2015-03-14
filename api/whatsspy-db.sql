-- Sequence: status_sid_seq

-- DROP SEQUENCE status_sid_seq;

CREATE SEQUENCE status_sid_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;
ALTER TABLE status_sid_seq
  OWNER TO whatsspy;
GRANT ALL ON TABLE status_sid_seq TO whatsspy;

-- Table: accounts

-- DROP TABLE accounts;

CREATE TABLE accounts
(
  id character varying(50) NOT NULL,
  active boolean NOT NULL DEFAULT true,
  name character varying(255),
  lastseen_privacy boolean NOT NULL DEFAULT false,
  verified boolean NOT NULL DEFAULT false,
  statusmessage_privacy boolean NOT NULL DEFAULT false,
  profilepic_privacy boolean NOT NULL DEFAULT false,
  notify_actions boolean NOT NULL DEFAULT false,
  CONSTRAINT pk_phone_id PRIMARY KEY (id)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE accounts
  OWNER TO whatsspy;
GRANT ALL ON TABLE accounts TO whatsspy;

-- Table: lastseen_privacy_history

-- DROP TABLE lastseen_privacy_history;

CREATE TABLE lastseen_privacy_history
(
  "number" character varying(50) NOT NULL,
  changed_at timestamp with time zone NOT NULL,
  privacy boolean NOT NULL,
  CONSTRAINT privacy_history_pkey PRIMARY KEY (number, changed_at),
  CONSTRAINT fk_lastseen_privacy_number FOREIGN KEY ("number")
      REFERENCES accounts (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);
ALTER TABLE lastseen_privacy_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE lastseen_privacy_history TO whatsspy;

-- Index: index_lastseen_number_changed_at

-- DROP INDEX index_lastseen_number_changed_at;

CREATE INDEX index_lastseen_number_changed_at
  ON lastseen_privacy_history
  USING btree
  (number COLLATE pg_catalog."default", changed_at DESC NULLS LAST);


-- Table: profilepic_privacy_history

-- DROP TABLE profilepic_privacy_history;

CREATE TABLE profilepic_privacy_history
(
  "number" character varying(50) NOT NULL,
  changed_at timestamp with time zone NOT NULL,
  privacy boolean NOT NULL,
  CONSTRAINT pk_profilepic_privacy PRIMARY KEY (number, changed_at),
  CONSTRAINT fk_profilepic_privacy_number FOREIGN KEY ("number")
      REFERENCES accounts (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);
ALTER TABLE profilepic_privacy_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE profilepic_privacy_history TO whatsspy;

-- Index: index_profilepic_number_changed_at

-- DROP INDEX index_profilepic_number_changed_at;

CREATE INDEX index_profilepic_number_changed_at
  ON profilepic_privacy_history
  USING btree
  (number COLLATE pg_catalog."default", changed_at DESC NULLS LAST);


-- Table: profilepicture_history

-- DROP TABLE profilepicture_history;

CREATE TABLE profilepicture_history
(
  "number" character varying(50) NOT NULL,
  hash character(64) NOT NULL,
  changed_at timestamp with time zone NOT NULL DEFAULT now(),
  CONSTRAINT profilepicture_history_pkey PRIMARY KEY (number, changed_at),
  CONSTRAINT fk_profilepicture_history_number FOREIGN KEY ("number")
      REFERENCES accounts (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);
ALTER TABLE profilepicture_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE profilepicture_history TO whatsspy;

-- Index: index_profilepicture_data_number_changed_at

-- DROP INDEX index_profilepicture_data_number_changed_at;

CREATE INDEX index_profilepicture_data_number_changed_at
  ON profilepicture_history
  USING btree
  (number COLLATE pg_catalog."default", changed_at DESC NULLS LAST);

-- Table: status_history

-- DROP TABLE status_history;

CREATE TABLE status_history
(
  status boolean NOT NULL,
  start timestamp with time zone NOT NULL DEFAULT now(),
  "end" timestamp with time zone,
  "number" character varying(50) NOT NULL,
  sid bigint NOT NULL DEFAULT nextval('status_sid_seq'::regclass),
  CONSTRAINT pk_sid PRIMARY KEY (sid),
  CONSTRAINT fk_number_id FOREIGN KEY ("number")
      REFERENCES accounts (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);
ALTER TABLE status_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE status_history TO whatsspy;

-- Index: index_end_status

-- DROP INDEX index_end_status;

CREATE INDEX index_end_status
  ON status_history
  USING btree
  ("end");

-- Index: index_number_status

-- DROP INDEX index_number_status;

CREATE INDEX index_number_status
  ON status_history
  USING btree
  (number COLLATE pg_catalog."default");

-- Index: index_start_status

-- DROP INDEX index_start_status;

CREATE INDEX index_start_status
  ON status_history
  USING btree
  (start);

-- Index: index_status_status

-- DROP INDEX index_status_status;

CREATE INDEX index_status_status
  ON status_history
  USING btree
  (status);

-- Index: number_status_analytics_count

-- DROP INDEX number_status_analytics_count;

CREATE INDEX number_status_analytics_count
  ON status_history
  USING btree
  (number COLLATE pg_catalog."default");

-- Index: number_status_analytics_result

-- DROP INDEX number_status_analytics_result;

CREATE INDEX number_status_analytics_result
  ON status_history
  USING btree
  (start, status, number COLLATE pg_catalog."default")
  WHERE "end" IS NOT NULL AND status = true;

-- Table: statusmessage_history

-- DROP TABLE statusmessage_history;

CREATE TABLE statusmessage_history
(
  "number" character varying(50) NOT NULL,
  status character varying(255) NOT NULL,
  changed_at timestamp with time zone NOT NULL,
  CONSTRAINT pk_statusmessage_history PRIMARY KEY (number, changed_at),
  CONSTRAINT fk_statusmessage_history FOREIGN KEY ("number")
      REFERENCES accounts (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);
ALTER TABLE statusmessage_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE statusmessage_history TO whatsspy;

-- Index: index_statusmessage_data_number_changed_at

-- DROP INDEX index_statusmessage_data_number_changed_at;

CREATE INDEX index_statusmessage_data_number_changed_at
  ON statusmessage_history
  USING btree
  (number COLLATE pg_catalog."default", changed_at DESC NULLS LAST);


-- Table: statusmessage_privacy_history

-- DROP TABLE statusmessage_privacy_history;

CREATE TABLE statusmessage_privacy_history
(
  "number" character varying(50) NOT NULL,
  changed_at timestamp with time zone NOT NULL,
  privacy boolean NOT NULL,
  CONSTRAINT pk_statusmessage_privacy PRIMARY KEY (number, changed_at),
  CONSTRAINT fk_statusmessage_privacy_number FOREIGN KEY ("number")
      REFERENCES accounts (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);
ALTER TABLE statusmessage_privacy_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE statusmessage_privacy_history TO whatsspy;

-- Index: index_statusmessage_number_changed_at

-- DROP INDEX index_statusmessage_number_changed_at;

CREATE INDEX index_statusmessage_number_changed_at
  ON statusmessage_privacy_history
  USING btree
  (number COLLATE pg_catalog."default", changed_at DESC NULLS LAST);


-- Table: tracker_history

-- DROP TABLE tracker_history;

CREATE TABLE tracker_history
(
  start timestamp with time zone NOT NULL DEFAULT now(),
  "end" timestamp with time zone,
  reason character varying(255),
  CONSTRAINT pk_tracker_history PRIMARY KEY (start)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE tracker_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE tracker_history TO whatsspy;



CREATE TABLE whatsspy_config
(
   db_version integer
) 
WITH (
  OIDS = FALSE
)
;
ALTER TABLE whatsspy_config
  OWNER TO whatsspy;
GRANT ALL ON TABLE whatsspy_config TO whatsspy;

INSERT INTO whatsspy_config (db_version)
    VALUES (4);


-- Add notification options
  ALTER TABLE accounts RENAME notify_actions  TO notify_status;
ALTER TABLE accounts
  ADD COLUMN notify_statusmsg boolean NOT NULL DEFAULT false;
ALTER TABLE accounts
  ADD COLUMN notify_profilepic boolean NOT NULL DEFAULT false;
CREATE INDEX index_account_notify_status
    ON accounts (notify_status ASC NULLS LAST);
  CREATE INDEX index_account_notify_statusmsg
    ON accounts (notify_statusmsg ASC NULLS LAST);
CREATE INDEX index_account_notify_profilepic
   ON accounts (notify_profilepic ASC NULLS LAST);

-- Add groups
CREATE TABLE groups
(
  gid serial NOT NULL,
  name character varying(255) NOT NULL,
  CONSTRAINT pk_groups_gid PRIMARY KEY (gid)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE groups
  OWNER TO postgres;
GRANT ALL ON TABLE groups TO whatsspy;
ALTER TABLE accounts
  ADD COLUMN group_id integer;
ALTER TABLE accounts
  ADD CONSTRAINT fk_group_id FOREIGN KEY (group_id) REFERENCES groups (gid) ON UPDATE NO ACTION ON DELETE NO ACTION;


-- Function: lastseen_privacy_update()

-- DROP FUNCTION lastseen_privacy_update();

CREATE OR REPLACE FUNCTION lastseen_privacy_update()
  RETURNS trigger AS
$BODY$
BEGIN

IF (NEW)."lastseen_privacy" != (OLD)."lastseen_privacy" THEN
	INSERT INTO lastseen_privacy_history ("number", "changed_at", "privacy") 
		VALUES((NEW).id, NOW(), (NEW)."lastseen_privacy");
END IF;

RETURN NULL;

END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION lastseen_privacy_update()
  OWNER TO whatsspy;

-- Function: profilepic_privacy_update()

-- DROP FUNCTION profilepic_privacy_update();

CREATE OR REPLACE FUNCTION profilepic_privacy_update()
  RETURNS trigger AS
$BODY$
BEGIN

IF (NEW)."profilepic_privacy" != (OLD)."profilepic_privacy" THEN
	INSERT INTO profilepic_privacy_history ("number", "changed_at", "privacy") 
		VALUES((NEW).id, NOW(), (NEW)."profilepic_privacy");
END IF;

RETURN NULL;

END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION profilepic_privacy_update()
  OWNER TO whatsspy;

-- Function: statusmessage_privacy_update()

-- DROP FUNCTION statusmessage_privacy_update();

CREATE OR REPLACE FUNCTION statusmessage_privacy_update()
  RETURNS trigger AS
$BODY$
BEGIN

IF (NEW)."statusmessage_privacy" != (OLD)."statusmessage_privacy" THEN
	INSERT INTO statusmessage_privacy_history ("number", "changed_at", "privacy") 
		VALUES((NEW).id, NOW(), (NEW)."statusmessage_privacy");
END IF;

RETURN NULL;

END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION statusmessage_privacy_update()
  OWNER TO whatsspy;

-- Index: index_account_active_verified

-- DROP INDEX index_account_active_verified;

CREATE INDEX index_account_active_verified
  ON accounts
  USING btree
  (active, verified);

-- Index: index_account_name

-- DROP INDEX index_account_name;

CREATE INDEX index_account_name
  ON accounts
  USING btree
  (name COLLATE pg_catalog."default");

-- 1.3.6 Index updates.

CREATE INDEX index_account_id
   ON accounts (id ASC NULLS LAST);

CREATE INDEX index_tracker_history_end
   ON tracker_history ("end" ASC NULLS FIRST);

CREATE INDEX index_tracker_history_start
   ON tracker_history ("start" DESC);

CREATE INDEX index_tracker_history_start_end_not_null
   ON tracker_history ("start" DESC) WHERE "end" IS NOT NULL;

CREATE INDEX index_status_history_end_status
   ON status_history (status ASC NULLS LAST, "end" ASC NULLS FIRST);

CREATE INDEX index_status_history_number_end
   ON status_history ("number" ASC NULLS LAST, "end" ASC NULLS FIRST);

CREATE INDEX index_profilepicture_history_number
   ON profilepicture_history ("number" ASC NULLS LAST);

CREATE INDEX index_statusmessage_number
   ON statusmessage_history ("number" ASC NULLS LAST);

CREATE INDEX index_accounts_active_true_verified_true
   ON accounts ("id") WHERE active = true AND verified = true;

CREATE INDEX index_status_history_number_end_is_null
   ON status_history ("number") WHERE "end" = null;

CREATE INDEX index_status_history_number_status_true_end_not_null
   ON status_history ("number") WHERE status = true AND "end" IS NOT NULL;

CREATE INDEX index_status_history_number_start_status_true_end_not_null
   ON status_history ("number", "start") WHERE status = true AND "end" IS NOT NULL;

CREATE INDEX index_status_history_status_true
   ON status_history ("status") WHERE status = true;

CREATE INDEX index_status_history_number_status_true_start_desc
   ON status_history ("number", "start" DESC) WHERE status = true;

CREATE INDEX index_status_history_sid_start_end_status_true
   ON status_history ("sid" DESC, "start" DESC, "end" DESC) WHERE status = true;

CREATE INDEX index_status_history_number_start_asc
   ON status_history ("number", "start" ASC);

CREATE INDEX index_profilepicture_history_number_changed_at_desc
   ON profilepicture_history ("number", "changed_at" DESC);

CREATE INDEX index_statusmessage_history_number_changed_at_desc
   ON statusmessage_history ("number", "changed_at" DESC);

CREATE INDEX index_lastseen_privacy_history_number_changed_at_desc
   ON lastseen_privacy_history ("number", "changed_at" DESC);

CREATE INDEX index_profilepic_privacy_history_number_changed_at_desc
   ON profilepic_privacy_history ("number", "changed_at" DESC);

CREATE INDEX index_statusmessage_privacy_history_number_changed_at_desc
   ON statusmessage_privacy_history ("number", "changed_at" DESC);

CREATE INDEX index_account_group_id
                    ON accounts (group_id ASC NULLS LAST);
CREATE INDEX index_account_id_group_id
   ON accounts (id ASC NULLS LAST, group_id ASC NULLS LAST);


-- Trigger: trigger_lastseen on accounts

-- DROP TRIGGER trigger_lastseen ON accounts;

CREATE TRIGGER trigger_lastseen
  AFTER UPDATE
  ON accounts
  FOR EACH ROW
  EXECUTE PROCEDURE lastseen_privacy_update();

-- Trigger: trigger_profilepic on accounts

-- DROP TRIGGER trigger_profilepic ON accounts;

CREATE TRIGGER trigger_profilepic
  AFTER UPDATE
  ON accounts
  FOR EACH ROW
  EXECUTE PROCEDURE profilepic_privacy_update();

-- Trigger: trigger_statusmessage on accounts

-- DROP TRIGGER trigger_statusmessage ON accounts;

CREATE TRIGGER trigger_statusmessage
  AFTER UPDATE
  ON accounts
  FOR EACH ROW
  EXECUTE PROCEDURE statusmessage_privacy_update();














