ALTER TABLE `User` ADD COLUMN `agentSubscriptionExpiresAt` DATETIME(3) NULL;

UPDATE `User`
SET `agentSubscriptionExpiresAt` = DATE_ADD(UTC_TIMESTAMP(3), INTERVAL 30 DAY)
WHERE `role` = 'AGENT' AND `agentSubscriptionExpiresAt` IS NULL;

CREATE INDEX `User_agentSubscriptionExpiresAt_idx` ON `User`(`agentSubscriptionExpiresAt`);
