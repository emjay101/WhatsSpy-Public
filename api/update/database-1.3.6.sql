
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

CREATE TABLE whatsspy_config
(
   db_version integer
) 
WITH (
  OIDS = FALSE
);
ALTER TABLE whatsspy_config
  OWNER TO whatsspy;
GRANT ALL ON TABLE whatsspy_config TO whatsspy;

INSERT INTO whatsspy_config (db_version)
    VALUES (3);