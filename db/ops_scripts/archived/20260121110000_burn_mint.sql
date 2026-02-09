INSERT INTO mints (mintid, day, gems_in_token_ratio, createdat)
VALUES (
  '3f4a9d21-8c6b-4c57-a1c2-9f0b7e2d6a91',
  '2026-01-20',
  9999999999.9999999999,
  '2026-01-22 10:02:04.212979'
);


INSERT INTO transactions (
  transactionid,
  operationid,
  transactiontype,
  senderid,
  recipientid,
  tokenamount,
  transferaction,
  message,
  createdat,
  transactioncategory
)
VALUES (
  '5c7e3b92-4e1a-4f0d-9a63-7a2f1d8e9c44',
  'a1f9c6e4-2b8d-4d77-9f3c-6b5e2d4a8f91',
  'transferMintAccountToRecipient',
  '00000000-0000-0000-0000-000000000001',
  '7e0b2d21-d2b0-4af5-8b73-5f8efc04b000',
  5000.0000000000,
  'CREDIT',
  NULL,
  '2026-01-21 10:05:00.847321',
  'TOKEN_MINT'
);
