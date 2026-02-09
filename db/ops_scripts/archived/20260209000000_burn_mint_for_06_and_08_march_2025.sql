INSERT INTO mints (mintid, day, gems_in_token_ratio, createdat)
VALUES (
  '4f5fd08c-6848-412a-8f60-5605121db898',
  '2025-03-06',
  9999999999.9999999999,
  '2026-03-06 09:00:00.001'
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
  'c415a7ac-3a43-4cb4-a9da-6b88ead486ed',
  '3f95d4d5-fba9-4df9-82d8-807dd84b71ef',
  'transferMintAccountToRecipient',
  '00000000-0000-0000-0000-000000000001',
  '7e0b2d21-d2b0-4af5-8b73-5f8efc04b000',
  5000.0000000000,
  'CREDIT',
  NULL,
  '2026-03-06 09:00:00.001',
  'TOKEN_MINT'
);



INSERT INTO mints (mintid, day, gems_in_token_ratio, createdat)
VALUES (
  'eb341161-cfd1-45a6-b386-480cc96e681c',
  '2025-03-08',
  9999999999.9999999999,
  '2025-03-08 09:00:00.001'
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
  '3b33ddcf-5d48-4214-b1f5-4a5c87616947',
  'be18b7ff-6364-48ea-a24a-12ef80fd550a',
  'transferMintAccountToRecipient',
  '00000000-0000-0000-0000-000000000001',
  '7e0b2d21-d2b0-4af5-8b73-5f8efc04b000',
  5000.0000000000,
  'CREDIT',
  NULL,
  '2026-03-08 09:00:00.001',
  'TOKEN_MINT'
);