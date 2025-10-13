-- Ensure sender and recipient can’t be the same
ALTER TABLE transactions
  ADD CONSTRAINT chk_not_self_transfer CHECK (senderid IS NULL OR senderid <> recipientid);

-- Enforce amount > 0
ALTER TABLE transactions
  ADD CONSTRAINT chk_positive_amount CHECK (tokenamount > 0);
