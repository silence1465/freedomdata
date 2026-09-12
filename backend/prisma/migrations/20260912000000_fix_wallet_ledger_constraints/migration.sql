-- Allow an order to have both purchase and refund ledger entries.
CREATE INDEX `WalletLedger_orderId_idx` ON `WalletLedger`(`orderId`);
DROP INDEX `WalletLedger_orderId_key` ON `WalletLedger`;

-- Make external payment/refund references the idempotency boundary.
CREATE UNIQUE INDEX `WalletLedger_reference_key` ON `WalletLedger`(`reference`);
