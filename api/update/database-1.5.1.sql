ALTER TABLE whatsspy_config
	ADD COLUMN account_show_timeline_length integer NOT NULL DEFAULT 14;
ALTER TABLE whatsspy_config
	ADD COLUMN account_show_timeline_tracker boolean NOT NULL DEFAULT true;
UPDATE whatsspy_config SET db_version = 8;