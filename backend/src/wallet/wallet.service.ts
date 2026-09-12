import { Injectable, BadRequestException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { LedgerType, Prisma } from '@prisma/client';
import { RealtimeGateway } from '../realtime/realtime.gateway';

@Injectable()
export class WalletService {
  constructor(
    private prisma: PrismaService,
    private realtime: RealtimeGateway,
  ) {}

  /** Credits a user's wallet (top-up, refund, admin adjustment). */
  async credit(userId: string, amount: number, type: LedgerType, opts?: { orderId?: string; reference?: string }) {
    return this.mutate(userId, amount, type, opts);
  }

  /** Debits a user's wallet (purchase). Throws if balance is insufficient. */
  async debit(userId: string, amount: number, type: LedgerType, opts?: { orderId?: string; reference?: string }) {
    return this.mutate(userId, -amount, type, opts);
  }

  private async mutate(
    userId: string,
    signedAmount: number,
    type: LedgerType,
    opts?: { orderId?: string; reference?: string },
  ) {
    const amount = new Prisma.Decimal(signedAmount);
    if (!amount.isFinite() || amount.isZero()) {
      throw new BadRequestException('invalid_amount');
    }

    let result: Awaited<ReturnType<typeof this.runMutation>> | undefined;
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        result = await this.runMutation(userId, amount, type, opts);
        break;
      } catch (err) {
        const retryable = err instanceof Prisma.PrismaClientKnownRequestError && err.code === 'P2034';
        if (!retryable || attempt === 2) throw err;
      }
    }

    if (!result) throw new Error('wallet_transaction_failed');
    this.realtime.emitWalletUpdate(userId, {
      balance: result.user.walletBalance,
      lastEntry: result.entry,
    });
    return result;
  }

  private runMutation(
    userId: string,
    signedAmount: Prisma.Decimal,
    type: LedgerType,
    opts?: { orderId?: string; reference?: string },
  ) {
    return this.prisma.$transaction(
      async (tx) => {
        const user = await tx.user.findUniqueOrThrow({ where: { id: userId } });
        const newBalance = user.walletBalance.plus(signedAmount);
        if (newBalance.isNegative()) {
          throw new BadRequestException('insufficient_balance');
        }

        const updated = await tx.user.update({
          where: { id: userId },
          data: { walletBalance: newBalance },
        });
        const entry = await tx.walletLedger.create({
          data: {
            userId,
            orderId: opts?.orderId,
            type,
            amount: signedAmount,
            balanceAfter: newBalance,
            reference: opts?.reference,
          },
        });
        return { user: updated, entry };
      },
      { isolationLevel: Prisma.TransactionIsolationLevel.Serializable },
    );
  }

  async getBalance(userId: string) {
    const user = await this.prisma.user.findUniqueOrThrow({ where: { id: userId } });
    return user.walletBalance;
  }

  async history(userId: string) {
    return this.prisma.walletLedger.findMany({
      where: { userId },
      orderBy: { createdAt: 'desc' },
      take: 100,
    });
  }
}
