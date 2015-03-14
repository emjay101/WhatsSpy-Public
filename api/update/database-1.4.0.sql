-- Add tracker reason
ALTER TABLE tracker_history
  ADD COLUMN reason character varying(255);

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
  OWNER TO whatsspy;
GRANT ALL ON TABLE groups TO whatsspy;
ALTER TABLE accounts
  ADD COLUMN group_id integer;
ALTER TABLE accounts
  ADD CONSTRAINT fk_group_id FOREIGN KEY (group_id) REFERENCES groups (gid) ON UPDATE NO ACTION ON DELETE NO ACTION;

  CREATE INDEX index_account_group_id
   									ON accounts (group_id ASC NULLS LAST);
CREATE INDEX index_account_id_group_id
   ON accounts (id ASC NULLS LAST, group_id ASC NULLS LAST);

UPDATE whatsspy_config SET db_version = 4;