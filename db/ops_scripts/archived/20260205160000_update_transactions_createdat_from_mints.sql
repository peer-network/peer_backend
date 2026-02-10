select * from gems where mintid = '083bccfe-fd68-4424-81a0-64c289212e85';
UPDATE transactions t
SET createdat =
    (m.day::date + TIME '09:00:00.000')
    + INTERVAL '1 day'
    + INTERVAL '000001 milliseconds'
FROM gems g
JOIN mints m ON m.mintid = g.mintid
WHERE t.transactioncategory = 'TOKEN_MINT'
  AND g.transactionid = t.transactionid;