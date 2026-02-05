-- updated createdat field to the day of mint
UPDATE mints
  SET createdat = (day::date + time '09:00:00.000') + INTERVAL '000001 milliseconds' ;