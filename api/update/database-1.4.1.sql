CREATE TABLE accounts_to_groups
(
  "number" character(50) NOT NULL,
  gid integer NOT NULL,
  CONSTRAINT pk_account_to_group PRIMARY KEY (number, gid)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE accounts_to_groups
  OWNER TO whatsspy;


INSERT INTO accounts_to_groups (number, gid) (SELECT id, group_id FROM accounts WHERE group_id IS NOT NULL);

ALTER TABLE accounts
  DROP COLUMN group_id;

ALTER TABLE accounts
  ADD COLUMN notify_timeline boolean NOT NULL DEFAULT false;

UPDATE whatsspy_config SET db_version = 5;