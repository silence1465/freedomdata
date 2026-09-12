-- CreateTable
CREATE TABLE `User` (
    `id` VARCHAR(191) NOT NULL,
    `phone` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NULL,
    `passwordHash` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NULL,
    `role` ENUM('CUSTOMER', 'AGENT', 'ADMIN') NOT NULL DEFAULT 'CUSTOMER',
    `walletBalance` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `User_phone_key`(`phone`),
    UNIQUE INDEX `User_email_key`(`email`),
    INDEX `User_role_idx`(`role`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Product` (
    `id` VARCHAR(191) NOT NULL,
    `dataSikaId` VARCHAR(191) NOT NULL,
    `network` VARCHAR(191) NOT NULL,
    `bundleGb` DECIMAL(6, 2) NOT NULL,
    `costPrice` DECIMAL(10, 2) NOT NULL,
    `sellPrice` DECIMAL(10, 2) NOT NULL,
    `agentPrice` DECIMAL(10, 2) NULL,
    `serviceType` ENUM('DATA_BUNDLE', 'MTN_EXPRESS', 'RESULT_CHECKER') NOT NULL DEFAULT 'DATA_BUNDLE',
    `isAvailable` BOOLEAN NOT NULL DEFAULT true,
    `updatedAt` DATETIME(3) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `Product_dataSikaId_key`(`dataSikaId`),
    INDEX `Product_network_serviceType_idx`(`network`, `serviceType`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Order` (
    `id` VARCHAR(191) NOT NULL,
    `idempotencyKey` VARCHAR(191) NOT NULL,
    `dataSikaOrderId` VARCHAR(191) NULL,
    `userId` VARCHAR(191) NOT NULL,
    `productId` VARCHAR(191) NOT NULL,
    `recipient` VARCHAR(191) NOT NULL,
    `amountCharged` DECIMAL(10, 2) NOT NULL,
    `costAmount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('PENDING', 'PROCESSING', 'DELIVERED', 'FAILED', 'REFUNDED', 'REFUND_PROCESSING') NOT NULL DEFAULT 'PENDING',
    `failureReason` VARCHAR(191) NULL,
    `pollAttempts` INTEGER NOT NULL DEFAULT 0,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `Order_idempotencyKey_key`(`idempotencyKey`),
    UNIQUE INDEX `Order_dataSikaOrderId_key`(`dataSikaOrderId`),
    INDEX `Order_userId_idx`(`userId`),
    INDEX `Order_status_idx`(`status`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `WalletLedger` (
    `id` VARCHAR(191) NOT NULL,
    `userId` VARCHAR(191) NOT NULL,
    `orderId` VARCHAR(191) NULL,
    `type` ENUM('TOPUP', 'PURCHASE', 'REFUND', 'ADJUSTMENT') NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `balanceAfter` DECIMAL(10, 2) NOT NULL,
    `reference` VARCHAR(191) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `WalletLedger_orderId_key`(`orderId`),
    INDEX `WalletLedger_userId_idx`(`userId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `Order` ADD CONSTRAINT `Order_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Order` ADD CONSTRAINT `Order_productId_fkey` FOREIGN KEY (`productId`) REFERENCES `Product`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `WalletLedger` ADD CONSTRAINT `WalletLedger_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `WalletLedger` ADD CONSTRAINT `WalletLedger_orderId_fkey` FOREIGN KEY (`orderId`) REFERENCES `Order`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
