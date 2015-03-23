ALTER TABLE accounts
  ADD COLUMN notify_privacy boolean NOT NULL DEFAULT false;

UPDATE whatsspy_config SET db_version = 6;