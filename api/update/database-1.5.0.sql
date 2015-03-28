ALTER TABLE accounts
	ADD COLUMN read_only_token character varying(255);
ALTER TABLE groups
	ADD COLUMN read_only_token character varying(255);
ALTER TABLE whatsspy_config
	ADD COLUMN last_login_attempt timestamp with time zone;

UPDATE whatsspy_config SET db_version = 7;