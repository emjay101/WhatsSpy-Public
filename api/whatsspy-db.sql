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
  CONSTRAINT pk_tracker_history PRIMARY KEY (start)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE tracker_history
  OWNER TO whatsspy;
GRANT ALL ON TABLE tracker_history TO whatsspy;


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











