ALTER TABLE `User` ADD COLUMN `agentCode` VARCHAR(191) NULL;
CREATE UNIQUE INDEX `User_agentCode_key` ON `User`(`agentCode`);

CREATE TABLE `AgentStoreOrder` (
    `id` VARCHAR(191) NOT NULL,
    `agentId` VARCHAR(191) NOT NULL,
    `productId` VARCHAR(191) NOT NULL,
    `orderId` VARCHAR(191) NULL,
    `recipient` VARCHAR(191) NOT NULL,
    `customerName` VARCHAR(191) NULL,
    `customerPhone` VARCHAR(191) NULL,
    `transactionId` VARCHAR(191) NULL,
    `proofData` LONGBLOB NULL,
    `proofMime` VARCHAR(191) NULL,
    `status` ENUM('SUBMITTED', 'HOLD', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'SUBMITTED',
    `reviewNote` VARCHAR(191) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,
    `reviewedAt` DATETIME(3) NULL,

    UNIQUE INDEX `AgentStoreOrder_orderId_key`(`orderId`),
    UNIQUE INDEX `AgentStoreOrder_transactionId_key`(`transactionId`),
    INDEX `AgentStoreOrder_agentId_status_idx`(`agentId`, `status`),
    INDEX `AgentStoreOrder_createdAt_idx`(`createdAt`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `AgentStoreOrder` ADD CONSTRAINT `AgentStoreOrder_agentId_fkey` FOREIGN KEY (`agentId`) REFERENCES `User`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `AgentStoreOrder` ADD CONSTRAINT `AgentStoreOrder_productId_fkey` FOREIGN KEY (`productId`) REFERENCES `Product`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `AgentStoreOrder` ADD CONSTRAINT `AgentStoreOrder_orderId_fkey` FOREIGN KEY (`orderId`) REFERENCES `Order`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
