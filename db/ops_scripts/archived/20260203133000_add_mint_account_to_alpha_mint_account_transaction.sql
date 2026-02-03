-- transfer tokens from mint_account to alpha_mint_account for alpha mint + lp account

insert into transactions (
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
values (
    '3893f5d5-5834-4590-a690-812cb83c1cb3',
    'a8cb4b8b-f216-4f73-9b8f-36dd51c7533b',
    'transferSenderToRecipient',
    '00000000-0000-0000-0000-000000000001', 
    '2736677b-57b8-4ee2-87fe-24ed975e55a6',
    402436.69,
    'CREDIT', 
    'Alpha Token Migration',
    '2025-09-01 09:00:00.00000',
    'P2P_TRANSFER'
)