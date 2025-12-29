BEGIN;

ALTER TABLE IF EXISTS public.logdaten
    DROP COLUMN IF EXISTS request_payload;

COMMIT;